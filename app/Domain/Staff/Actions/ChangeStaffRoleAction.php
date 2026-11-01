<?php

namespace App\Domain\Staff\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Models\Club;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Staff\DTOs\ChangeStaffRoleData;
use App\Domain\Subscription\Actions\EnsureWithinPlanLimitAction;
use App\Domain\Subscription\Enums\PlanLimit;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ChangeStaffRoleAction
{
    public function __construct(
        private readonly EnsureWithinPlanLimitAction $ensureWithinPlanLimit,
        private readonly RecordActivityAction $recordActivity,
    ) {}

    public function execute(User $actor, User $staffMember, ChangeStaffRoleData $data): User
    {
        if ($data->role === RoleName::Owner) {
            throw new BusinessRuleException('staff.owner_role_not_assignable', [], 422);
        }

        return DB::transaction(function () use ($actor, $staffMember, $data): User {
            $club = Club::query()->lockForUpdate()->findOrFail($actor->club_id);

            if ($staffMember->club_id !== $club->id) {
                throw (new ModelNotFoundException)->setModel(User::class, [$staffMember->id]);
            }

            $previousTeamId = getPermissionsTeamId();
            setPermissionsTeamId($club->id);

            try {
                $currentRoles = $staffMember->getRoleNames();

                if ($currentRoles->count() === 1 && $currentRoles->first() === $data->role->value) {
                    return $staffMember;
                }

                if ($staffMember->hasRole(RoleName::Owner->value)) {
                    throw new BusinessRuleException('staff.cannot_change_owner_role', [], 422);
                }

                $previousRole = $currentRoles->first();

                if ($data->role === RoleName::Coach && $previousRole !== RoleName::Coach->value) {
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

                $staffMember->syncRoles($data->role->value);

                $this->recordActivity->execute(
                    event: ActivityEvent::StaffRoleChanged,
                    subject: $staffMember,
                    properties: [
                        'user_id' => $staffMember->id,
                        'email' => $staffMember->email,
                        'from' => $previousRole,
                        'to' => $data->role->value,
                    ],
                    clubId: $club->id,
                    causer: $actor,
                );

                return $staffMember->refresh();
            } finally {
                setPermissionsTeamId($previousTeamId);
            }
        });
    }
}
