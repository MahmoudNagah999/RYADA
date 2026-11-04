<?php

namespace App\Domain\Player\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Player\Models\Player;
use Illuminate\Support\Facades\DB;

class DeletePlayerAction
{
    public function __construct(
        private readonly RecordActivityAction $recordActivity,
    ) {}

    public function execute(Player $player): void
    {
        DB::transaction(function () use ($player): void {
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
