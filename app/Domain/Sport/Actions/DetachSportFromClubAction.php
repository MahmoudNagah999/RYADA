<?php

namespace App\Domain\Sport\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Club\Models\Club;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Domain\Sport\Models\ClubSport;
use Illuminate\Support\Facades\DB;

class DetachSportFromClubAction
{
    public function __construct(
        private readonly CurrentClub $currentClub,
        private readonly RecordActivityAction $recordActivity,
    ) {}

    /**
     * Throws ModelNotFoundException (404) when the club does not offer the sport.
     * TODO (Step 4 / #20 / #21): block removal while the sport still has enrollments,
     * active membership plans or training groups (ADR-007).
     */
    public function execute(Club $club, int $sportId): void
    {
        DB::transaction(function () use ($club, $sportId): void {
            $this->currentClub->runAs($club, function () use ($club, $sportId): void {
                $clubSport = ClubSport::query()
                    ->with('sport')
                    ->where('sport_id', $sportId)
                    ->firstOrFail();

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
