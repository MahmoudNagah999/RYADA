<?php

namespace App\Domain\Sport\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Player\Models\Player;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Sport\Models\ClubSport;
use App\Domain\Sport\Models\PlayerSport;
use App\Domain\Sport\Models\Sport;
use Illuminate\Support\Facades\DB;

class EnrollPlayerInSportAction
{
    public function __construct(private readonly RecordActivityAction $recordActivity) {}

    /**
     * Runs in the current club context (the `tenant` middleware, or CurrentClub::runAs in jobs and tests).
     * Idempotent: an existing enrollment is returned and not logged.
     */
    public function execute(Player $player, Sport $sport): PlayerSport
    {
        return DB::transaction(function () use ($player, $sport): PlayerSport {
            $existing = PlayerSport::query()
                ->where('player_id', $player->id)
                ->where('sport_id', $sport->id)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            if (! ClubSport::query()->where('sport_id', $sport->id)->exists()) {
                throw new BusinessRuleException('player.sport_not_offered', [], 422);
            }

            if (! $sport->is_active) {
                throw new BusinessRuleException('sport.inactive', [], 422);
            }

            $enrollment = PlayerSport::query()->create([
                'player_id' => $player->id,
                'sport_id' => $sport->id,
            ]);

            $this->recordActivity->execute(
                ActivityEvent::PlayerSportEnrolled,
                $player,
                ['sport_id' => $sport->id, 'sport' => $sport->slug],
                null,
                null,
            );

            return $enrollment;
        });
    }
}
