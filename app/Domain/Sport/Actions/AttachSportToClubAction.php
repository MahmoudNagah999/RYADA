<?php

namespace App\Domain\Sport\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Club\Models\Club;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Domain\Sport\Models\ClubSport;
use App\Domain\Sport\Models\Sport;
use Illuminate\Support\Facades\DB;

class AttachSportToClubAction
{
    public function __construct(
        private readonly CurrentClub $currentClub,
        private readonly RecordActivityAction $recordActivity,
    ) {}

    /**
     * Idempotent: attaching a sport the club already offers returns the existing row (not logged).
     * An inactive sport cannot be newly attached (clubs that already have it keep it).
     */
    public function execute(Club $club, Sport $sport): ClubSport
    {
        return DB::transaction(function () use ($club, $sport): ClubSport {
            return $this->currentClub->runAs($club, function () use ($club, $sport): ClubSport {
                $existing = ClubSport::query()->where('sport_id', $sport->id)->first();

                if ($existing !== null) {
                    return $existing;
                }

                if (! $sport->is_active) {
                    throw new BusinessRuleException('sport.inactive', [], 422);
                }

                $clubSport = ClubSport::query()->create(['sport_id' => $sport->id]);

                $this->recordActivity->execute(
                    ActivityEvent::ClubSportAdded,
                    $club,
                    ['sport_id' => $sport->id, 'sport' => $sport->slug],
                    null,
                    null,
                );

                return $clubSport;
            });
        });
    }
}
