<?php

namespace App\Domain\Shared\Tenancy\Queue;

use App\Domain\Club\Models\Club;
use App\Domain\Shared\Tenancy\CurrentClub;
use Closure;
use LogicException;

/**
 * Job middleware: a worker has no request, so nobody sets the current club.
 * This loads the job's club, runs the job inside it (roles team included) and restores the previous context.
 * A job whose club no longer exists is dropped.
 */
final class RunsInClubContext
{
    public function handle(object $job, Closure $next): mixed
    {
        if (! $job instanceof TenantAwareJob) {
            throw new LogicException($job::class.' must implement '.TenantAwareJob::class.' to use '.self::class.'.');
        }

        $club = Club::query()->find($job->clubId());

        if ($club === null) {
            if (method_exists($job, 'delete')) {
                $job->delete();
            }

            return null;
        }

        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId($club->id);

        try {
            return app(CurrentClub::class)->runAs($club, fn () => $next($job));
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }
}
