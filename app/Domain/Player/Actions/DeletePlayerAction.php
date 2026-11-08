<?php

namespace App\Domain\Player\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Player\Models\Player;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\DB;

class DeletePlayerAction
{
    public function __construct(
        private readonly RecordActivityAction $recordActivity,
    ) {}

    public function execute(Player $player): void
    {
        DB::transaction(function () use ($player): void {
            // The FK restrict is only the backstop; answer with a business error first.
            if (PlayerSubscription::query()->where('player_id', $player->id)->exists()) {
                throw new BusinessRuleException('player.has_subscriptions', [], 422);
            }

            $this->recordActivity->execute(
                ActivityEvent::PlayerDeleted,
                $player,
                ['name' => $player->name],
                null,
                null,
            );

            $player->delete();
        });
    }
}
