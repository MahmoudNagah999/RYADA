<?php

namespace App\Domain\Sport\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Player\Models\Player;
use App\Domain\Sport\Models\PlayerSport;
use Illuminate\Support\Facades\DB;

class UnenrollPlayerFromSportAction
{
    public function __construct(private readonly RecordActivityAction $recordActivity) {}

    /**
     * Throws ModelNotFoundException (404) when the player is not enrolled in the sport.
     */
    public function execute(Player $player, int $sportId): void
    {
        DB::transaction(function () use ($player, $sportId): void {
            $enrollment = PlayerSport::query()
                ->with('sport')
                ->where('player_id', $player->id)
                ->where('sport_id', $sportId)
                ->firstOrFail();

            $slug = $enrollment->sport->slug;

            $enrollment->delete();

            $this->recordActivity->execute(
                ActivityEvent::PlayerSportUnenrolled,
                $player,
                ['sport_id' => $sportId, 'sport' => $slug],
                null,
                null,
            );
        });
    }
}
