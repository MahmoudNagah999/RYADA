<?php

namespace App\Domain\Staff\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Staff\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class RevokeStaffInvitationAction
{
    public function __construct(private readonly RecordActivityAction $recordActivity) {}

    public function execute(User $actor, StaffInvitation $invitation): StaffInvitation
    {
        return DB::transaction(function () use ($actor, $invitation): StaffInvitation {
            $lockedInvitation = StaffInvitation::query()
                ->where('club_id', $actor->club_id)
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            if ($lockedInvitation->accepted_at !== null) {
                throw (new ModelNotFoundException)->setModel(StaffInvitation::class, [$invitation->id]);
            }

            if ($lockedInvitation->revoked_at !== null) {
                return $lockedInvitation;
            }

            $lockedInvitation->revoked_at = now();
            $lockedInvitation->save();

            $this->recordActivity->execute(
                event: ActivityEvent::StaffInvitationRevoked,
                subject: $lockedInvitation,
                properties: [
                    'invitation_id' => $lockedInvitation->id,
                    'email' => $lockedInvitation->email,
                ],
                clubId: (int) $actor->club_id,
                causer: $actor,
            );

            return $lockedInvitation;
        });
    }
}
