<?php

namespace App\Domain\Sport\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Player\Models\Player;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Sport\Models\PlayerSport;
use App\Domain\Training\Models\TrainingGroup;
use App\Domain\Training\Models\TrainingGroupPlayer;
use Illuminate\Support\Facades\DB;

class UnenrollPlayerFromSportAction
{
    public function __construct(private readonly RecordActivityAction $recordActivity) {}

    /**
     * Throws ModelNotFoundException (404) when the player is not enrolled in the sport, and
     * `player.sport_has_groups` while the player is still in a training group of that sport.
     */
    public function execute(Player $player, int $sportId): void
    {
        DB::transaction(function () use ($player, $sportId): void {
            // Locking the enrollment first serializes with AddPlayerToTrainingGroupAction.
            $enrollment = PlayerSport::query()
                ->with('sport')
                ->where('player_id', $player->id)
                ->where('sport_id', $sportId)
                ->lockForUpdate()
                ->firstOrFail();

            $inGroup = TrainingGroupPlayer::query()
                ->where('player_id', $player->id)
                ->whereIn('training_group_id', TrainingGroup::query()->where('sport_id', $sportId)->select('id'))
                ->exists();

            if ($inGroup) {
                throw new BusinessRuleException('player.sport_has_groups', [], 422);
            }

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
