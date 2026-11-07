<?php

namespace App\Domain\Training\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Player\Models\Player;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Sport\Models\PlayerSport;
use App\Domain\Training\Models\TrainingGroup;
use App\Domain\Training\Models\TrainingGroupPlayer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class AddPlayerToTrainingGroupAction
{
    public function __construct(private readonly RecordActivityAction $recordActivity) {}

    /**
     * Runs in the current club context. The group must be active and the player must be enrolled
     * in the group's sport. Idempotent: an existing membership is returned and not logged.
     */
    public function execute(TrainingGroup $group, Player $player): TrainingGroupPlayer
    {
        return DB::transaction(function () use ($group, $player): TrainingGroupPlayer {
            $existing = TrainingGroupPlayer::query()
                ->where('training_group_id', $group->id)
                ->where('player_id', $player->id)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            if (! $group->is_active) {
                throw new BusinessRuleException('training_group.inactive', [], 422);
            }

            // Locks the enrollment row so a concurrent unenroll cannot slip past its group check.
            $enrolled = PlayerSport::query()
                ->where('player_id', $player->id)
                ->where('sport_id', $group->sport_id)
                ->lockForUpdate()
                ->exists();

            if (! $enrolled) {
                throw new BusinessRuleException('training_group.player_not_enrolled', [], 422);
            }

            try {
                $membership = TrainingGroupPlayer::query()->create([
                    'training_group_id' => $group->id,
                    'player_id' => $player->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Two concurrent identical requests: the unique index is the backstop.
                return TrainingGroupPlayer::query()
                    ->where('training_group_id', $group->id)
                    ->where('player_id', $player->id)
                    ->firstOrFail();
            }

            $this->recordActivity->execute(
                ActivityEvent::TrainingGroupPlayerAdded,
                $group,
                ['group_id' => $group->id, 'player_id' => $player->id],
                null,
                null,
            );

            return $membership;
        });
    }
}
