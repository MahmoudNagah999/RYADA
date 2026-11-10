<?php

namespace Tests\Support\Queue;

use App\Domain\Player\Models\Player;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Domain\Shared\Tenancy\Queue\TenantQueuedJob;

class RecordingClubJob extends TenantQueuedJob
{
    /** @var list<array{club_id: int|null, players: int, team_id: mixed}> */
    public static array $runs = [];

    public static function reset(): void
    {
        self::$runs = [];
    }

    public function handle(CurrentClub $currentClub): void
    {
        self::$runs[] = [
            'club_id' => $currentClub->get()?->id,
            'players' => Player::query()->count(),
            'team_id' => getPermissionsTeamId(),
        ];
    }
}
