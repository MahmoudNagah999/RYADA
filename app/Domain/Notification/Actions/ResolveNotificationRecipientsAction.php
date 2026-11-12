<?php

namespace App\Domain\Notification\Actions;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ResolveNotificationRecipientsAction
{
    /**
     * Only Owners and Staff receive club notifications; coaches and referees never do.
     */
    private const ROLES = [RoleName::Owner->value, RoleName::Staff->value];

    /**
     * Roles are per club (teams mode), so the team is set explicitly and restored.
     * `User` has no club scope, therefore `club_id` is filtered here by hand.
     *
     * @return Collection<int, User>
     */
    public function execute(Club $club): Collection
    {
        $previousTeamId = getPermissionsTeamId();

        setPermissionsTeamId($club->id);

        try {
            return User::query()
                ->where('club_id', $club->id)
                ->whereHas('roles', fn ($roles) => $roles->whereIn('roles.name', self::ROLES))
                ->orderBy('id')
                ->get();
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }
}
