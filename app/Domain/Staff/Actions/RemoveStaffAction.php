<?php

namespace App\Domain\Staff\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Models\Club;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class RemoveStaffAction
{
    public function __construct(private readonly RecordActivityAction $recordActivity) {}

    public function execute(User $actor, User $staffMember): void
    {
        DB::transaction(function () use ($actor, $staffMember): void {
            $club = Club::query()->lockForUpdate()->findOrFail($actor->club_id);

            if ($staffMember->club_id !== $club->id) {
                throw (new ModelNotFoundException)->setModel(User::class, [$staffMember->id]);
            }

            if ($actor->is($staffMember)) {
                throw new BusinessRuleException('staff.cannot_remove_self', [], 422);
            }

            $previousTeamId = getPermissionsTeamId();
            setPermissionsTeamId($club->id);

            try {
                if ($staffMember->hasRole(RoleName::Owner->value)) {
                    throw new BusinessRuleException('staff.cannot_remove_owner', [], 422);
                }

                $ownerCount = User::query()
                    ->where('club_id', $club->id)
                    ->whereHas('roles', fn ($roles) => $roles->where('roles.name', RoleName::Owner->value))
                    ->count();

                if ($ownerCount < 1) {
                    throw new BusinessRuleException('staff.last_owner_required', [], 422);
                }

                $role = $staffMember->getRoleNames()->first();

                $this->recordActivity->execute(
                    event: ActivityEvent::StaffRemoved,
                    subject: $staffMember,
                    properties: [
                        'user_id' => $staffMember->id,
                        'email' => $staffMember->email,
                        'role' => $role,
                    ],
                    clubId: $club->id,
                    causer: $actor,
                );

                $staffMember->tokens()->delete();
                $staffMember->syncRoles([]);
                $staffMember->delete();
            } finally {
                setPermissionsTeamId($previousTeamId);
            }
        });
    }
}
