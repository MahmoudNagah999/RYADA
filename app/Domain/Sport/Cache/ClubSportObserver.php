<?php

namespace App\Domain\Sport\Cache;

use App\Domain\Shared\Cache\TenantCache;
use App\Domain\Sport\Models\ClubSport;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ClubSportObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly TenantCache $cache) {}

    public function saved(ClubSport $clubSport): void
    {
        $this->cache->forgetForClub((int) $clubSport->club_id, ClubSportList::KEY);
    }

    public function deleted(ClubSport $clubSport): void
    {
        $this->cache->forgetForClub((int) $clubSport->club_id, ClubSportList::KEY);
    }
}
