<?php

namespace App\Http\Middleware;

use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the current tenant (and the spatie permissions team) from the authenticated user.
 * Must run after `auth:sanctum`.
 */
class ResolveCurrentClub
{
    public function __construct(private readonly CurrentClub $currentClub) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $club = $user instanceof User ? $user->club : null;

        if ($club === null) {
            throw new BusinessRuleException('tenant.no_club', [], 403);
        }

        $previousClub = $this->currentClub->get();
        $previousTeamId = getPermissionsTeamId();

        $this->currentClub->set($club);
        setPermissionsTeamId($club->id);

        // Drop any roles/permissions loaded before the team was set.
        $user->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return $next($request);
        } finally {
            $this->currentClub->set($previousClub);
            setPermissionsTeamId($previousTeamId);
        }
    }
}
