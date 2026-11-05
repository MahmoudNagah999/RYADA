<?php

namespace App\Domain\Sport\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Club\Models\Club;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Domain\Sport\Models\ClubSport;
use App\Domain\Sport\Models\PlayerSport;
use Illuminate\Support\Facades\DB;

class DetachSportFromClubAction
{
    public function __construct(
        private readonly CurrentClub $currentClub,
        private readonly RecordActivityAction $recordActivity,
    ) {}

    public function execute(Club $club, int $sportId): void
    {
        DB::transaction(function () use ($club, $sportId): void {
            $this->currentClub->runAs($club, function () use ($club, $sportId): void {
                $clubSport = ClubSport::query()
                    ->with('sport')
                    ->where('sport_id', $sportId)
                    ->firstOrFail();

                // ADR-007: a sport cannot be removed while it still has enrollments.
                // TODO:: #20 also block on active membership plans. TODO #21: also block on training groups.
                if (PlayerSport::query()->where('sport_id', $sportId)->exists()) {
                    throw new BusinessRuleException('player.sport_has_enrollments', [], 422);
                }

                $slug = $clubSport->sport->slug;

                $clubSport->delete();

                $this->recordActivity->execute(
                    ActivityEvent::ClubSportRemoved,
                    $club,
                    ['sport_id' => $sportId, 'sport' => $slug],
                    null,
                    null,
                );
            });
        });
    }
}
