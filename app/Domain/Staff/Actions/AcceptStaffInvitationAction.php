<?php

namespace App\Domain\Staff\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Auth\DTOs\LoginResult;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Models\Club;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Domain\Staff\DTOs\AcceptStaffInvitationData;
use App\Domain\Staff\Models\StaffInvitation;
use App\Domain\Subscription\Actions\EnsureWithinPlanLimitAction;
use App\Domain\Subscription\Enums\PlanLimit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AcceptStaffInvitationAction
{
    public function __construct(
        private readonly CurrentClub $currentClub,
        private readonly EnsureWithinPlanLimitAction $ensureWithinPlanLimit,
        private readonly RecordActivityAction $recordActivity,
    ) {}

    public function execute(AcceptStaffInvitationData $data): LoginResult
    {
        $tokenHash = hash('sha256', $data->token);

        return DB::transaction(function () use ($data, $tokenHash): LoginResult {
            $invitationPreview = DB::table('staff_invitations')
                ->where('token_hash', $tokenHash)
                ->first();

            if ($invitationPreview === null) {
                throw $this->invalidInvitation();
            }

            $club = Club::query()
                ->lockForUpdate()
                ->find($invitationPreview->club_id);

            if ($club === null) {
                throw $this->invalidInvitation();
            }

            return $this->currentClub->runAs($club, function () use ($data, $invitationPreview, $club, $tokenHash): LoginResult {
                $invitationRow = DB::table('staff_invitations')
                    ->where('id', $invitationPreview->id)
                    ->where('club_id', $club->id)
                    ->where('token_hash', $tokenHash)
                    ->lockForUpdate()
                    ->first();

                if ($invitationRow === null) {
                    throw $this->invalidInvitation();
                }

                if (
                    $invitationRow->accepted_at !== null
                    || $invitationRow->revoked_at !== null
                    || ! Carbon::parse($invitationRow->expires_at)->isFuture()
                ) {
                    throw $this->invalidInvitation();
                }

                $role = RoleName::tryFrom($invitationRow->role);

                if ($role === null || ! in_array($role, RoleName::assignableToStaff(), true)) {
                    throw $this->invalidInvitation();
                }

                $email = Str::lower($invitationRow->email);

                if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                    throw new BusinessRuleException('staff.email_taken', [], 422);
                }

                $previousTeamId = getPermissionsTeamId();
                setPermissionsTeamId($club->id);

                try {
                    if ($role === RoleName::Coach) {
                        $coachCount = User::query()
                            ->where('club_id', $club->id)
                            ->whereHas('roles', fn ($roles) => $roles->where('roles.name', RoleName::Coach->value))
                            ->count();

                        $this->ensureWithinPlanLimit->execute(
                            $club->load('plan'),
                            PlanLimit::Coaches,
                            $coachCount,
                        );
                    }

                    $user = new User([
                        'name' => $data->name,
                        'email' => $email,
                        'password' => $data->password,
                        'locale' => $data->locale,
                    ]);
                    $user->club()->associate($club);
                    $user->save();
                    $user->assignRole($role->value);

                    $invitation = StaffInvitation::query()
                        ->where('club_id', $club->id)
                        ->findOrFail($invitationRow->id);
                    $invitation->accepted_at = now();
                    $invitation->save();

                    $this->recordActivity->execute(
                        event: ActivityEvent::StaffJoined,
                        subject: $user,
                        properties: [
                            'user_id' => $user->id,
                            'invitation_id' => $invitation->id,
                            'email' => $email,
                            'role' => $role->value,
                        ],
                        clubId: $club->id,
                        causer: $user,
                    );

                    $token = $user->createToken('api', ['*'])->plainTextToken;

                    return new LoginResult($user, $token, ['*']);
                } finally {
                    setPermissionsTeamId($previousTeamId);
                }
            });
        });
    }

    private function invalidInvitation(): BusinessRuleException
    {
        return new BusinessRuleException('staff.invitation_invalid', [], 422);
    }
}
