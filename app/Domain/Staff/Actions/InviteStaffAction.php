<?php

namespace App\Domain\Staff\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Models\Club;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Domain\Staff\DTOs\InviteStaffData;
use App\Domain\Staff\Models\StaffInvitation;
use App\Mail\StaffInvitationMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;

class InviteStaffAction
{
    public function __construct(
        private readonly CurrentClub $currentClub,
        private readonly RecordActivityAction $recordActivity,
    ) {}

    public function execute(Club $club, User $invitedBy, InviteStaffData $data): StaffInvitation
    {
        if (! in_array($data->role, RoleName::assignableToStaff(), true)) {
            throw new BusinessRuleException('staff.owner_role_not_assignable', [], 422);
        }

        $plainToken = Str::random(64);

        $invitation = DB::transaction(function () use ($club, $invitedBy, $data, $plainToken): StaffInvitation {
            $lockedClub = Club::query()->lockForUpdate()->findOrFail($club->id);

            if (User::query()->whereRaw('LOWER(email) = ?', [Str::lower($data->email)])->exists()) {
                throw new BusinessRuleException('staff.email_taken', [], 422);
            }

            return $this->currentClub->runAs($lockedClub, function () use ($lockedClub, $invitedBy, $data, $plainToken): StaffInvitation {
                $pendingInvitation = StaffInvitation::query()
                    ->where('club_id', $lockedClub->id)
                    ->whereRaw('LOWER(email) = ?', [Str::lower($data->email)])
                    ->whereNull('accepted_at')
                    ->whereNull('revoked_at')
                    ->lockForUpdate()
                    ->first();

                if ($pendingInvitation !== null && $pendingInvitation->expires_at->isFuture()) {
                    throw new BusinessRuleException('staff.invitation_pending', [], 409);
                }

                if ($pendingInvitation !== null) {
                    $pendingInvitation->revoked_at = now();
                    $pendingInvitation->save();

                    $this->recordActivity->execute(
                        event: ActivityEvent::StaffInvitationRevoked,
                        subject: $pendingInvitation,
                        properties: [
                            'invitation_id' => $pendingInvitation->id,
                            'email' => $pendingInvitation->email,
                        ],
                        clubId: $lockedClub->id,
                        causer: $invitedBy,
                    );
                }

                $invitation = new StaffInvitation;
                $invitation->club()->associate($lockedClub);
                $invitation->fill([
                    'email' => Str::lower($data->email),
                    'role' => $data->role,
                    'token_hash' => hash('sha256', $plainToken),
                    'invited_by' => $invitedBy->id,
                    'expires_at' => now()->addDays((int) config('app.invitation_expiry_days', 7)),
                ]);
                $invitation->save();

                $this->recordActivity->execute(
                    event: ActivityEvent::StaffInvited,
                    subject: $invitation,
                    properties: [
                        'invitation_id' => $invitation->id,
                        'email' => $invitation->email,
                        'role' => $data->role->value,
                        'invited_by' => $invitedBy->id,
                    ],
                    clubId: $lockedClub->id,
                    causer: $invitedBy,
                );

                return $invitation;
            });
        });

        $acceptUrl = Uri::of((string) config('app.invitation_accept_url'))
            ->withQuery(['token' => $plainToken])
            ->toString();

        Mail::to($invitation->email)
            ->locale($data->locale)
            ->send((new StaffInvitationMail(
                acceptUrl: $acceptUrl,
                clubName: $club->name,
                role: $data->role,
                expiresAt: $invitation->expires_at,
            ))->afterCommit());

        return $invitation;
    }
}
