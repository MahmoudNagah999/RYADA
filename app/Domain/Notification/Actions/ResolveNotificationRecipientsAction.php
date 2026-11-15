<?php

namespace App\Domain\Notification\Actions;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ResolveNotificationRecipientsAction
{
    /**
     * Club notifications go to the Owners and the Staff by default; coaches and referees never
     * receive any. A caller can narrow the roles (the SaaS plan reminder is Owner only: the plan
     * is the owner's money).
     *
     * Roles are per club (teams mode), so the team is set explicitly and restored.
     * `User` has no club scope, therefore `club_id` is filtered here by hand.
     *
     * @param  list<RoleName>  $roles
     * @return Collection<int, User>
     */
    public function execute(Club $club, array $roles = [RoleName::Owner, RoleName::Staff]): Collection
    {
        $previousTeamId = getPermissionsTeamId();

        setPermissionsTeamId($club->id);

        try {
            return User::query()
                ->where('club_id', $club->id)
                ->whereHas('roles', fn ($query) => $query->whereIn('roles.name', $this->roleNames($roles)))
                ->orderBy('id')
                ->get();
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }

    /**
     * @param  list<RoleName>  $roles
     * @return list<string>
     */
    private function roleNames(array $roles): array
    {
        return array_map(static fn (RoleName $role): string => $role->value, $roles);
    }
}
