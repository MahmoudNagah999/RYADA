<?php

namespace App\Domain\Training\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Training\Models\TrainingGroup;
use App\Domain\Training\Models\TrainingGroupPlayer;
use Illuminate\Support\Facades\DB;

class RemovePlayerFromTrainingGroupAction
{
    public function __construct(private readonly RecordActivityAction $recordActivity) {}

    /**
     * Allowed for inactive groups too. Throws ModelNotFoundException (404) when the player
     * is not in the group.
     */
    public function execute(TrainingGroup $group, int $playerId): void
    {
        DB::transaction(function () use ($group, $playerId): void {
            $membership = TrainingGroupPlayer::query()
                ->where('training_group_id', $group->id)
                ->where('player_id', $playerId)
                ->firstOrFail();

            $membership->delete();

            $this->recordActivity->execute(
                ActivityEvent::TrainingGroupPlayerRemoved,
                $group,
                ['group_id' => $group->id, 'player_id' => $playerId],
                null,
                null,
            );
        });
    }
}
