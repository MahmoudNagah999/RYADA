<?php

namespace App\Domain\Shared\Tenancy\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Base class for every queued job that belongs to a club.
 * Subclasses take extra ids (never tenant-owned models) and write `handle()`;
 * when `handle()` runs, the current club and the roles team are already set.
 */
abstract class TenantQueuedJob implements ShouldQueue, TenantAwareJob
{
    use Dispatchable;
    use Queueable;

    public function __construct(public readonly int $clubId) {}

    public function clubId(): int
    {
        return $this->clubId;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RunsInClubContext];
    }
}
