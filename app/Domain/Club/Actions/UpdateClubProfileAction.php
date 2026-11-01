<?php

namespace App\Domain\Club\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Club\DTOs\UpdateClubProfileData;
use App\Domain\Club\Models\Club;
use Illuminate\Support\Facades\DB;

class UpdateClubProfileAction
{
    public function __construct(private readonly RecordActivityAction $recordActivity) {}

    public function execute(Club $club, UpdateClubProfileData $data): Club
    {
        return DB::transaction(function () use ($club, $data) {
            $club->fill($data->toAttributes());

            $changes = [];
            foreach ($club->getDirty() as $field => $to) {
                $changes[$field] = [
                    'from' => $club->getOriginal($field),
                    'to' => $to,
                ];
            }

            // Idempotent no-op: nothing changed, nothing saved, nothing logged.
            if ($changes === []) {
                return $club;
            }

            $club->save();

            $this->recordActivity->execute(
                event: ActivityEvent::ClubProfileUpdated,
                subject: $club,
                properties: ['changes' => $changes],
            );

            return $club;
        });
    }
}
