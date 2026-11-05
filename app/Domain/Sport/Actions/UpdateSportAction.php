<?php

namespace App\Domain\Sport\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Sport\DTOs\SportData;
use App\Domain\Sport\Models\Sport;
use Illuminate\Support\Facades\DB;

class UpdateSportAction
{
    public function __construct(private readonly RecordActivityAction $recordActivity) {}

    /**
     * The slug never changes after creation: v2.0 sport modules are linked by it (ADR-007).
     */
    public function execute(Sport $sport, SportData $data): Sport
    {
        return DB::transaction(function () use ($sport, $data): Sport {
            $before = [
                'name' => $sport->getTranslations('name'),
                'is_active' => $sport->is_active,
            ];

            $sport->fill([
                'name' => $data->name,
                'is_active' => $data->isActive,
            ]);

            $after = [
                'name' => $data->name,
                'is_active' => $data->isActive,
            ];

            $changes = [];
            foreach ($after as $field => $to) {
                if ($before[$field] != $to) {
                    $changes[$field] = ['from' => $before[$field], 'to' => $to];
                }
            }

            if ($changes === []) {
                return $sport;
            }

            $sport->save();

            $this->recordActivity->execute(
                ActivityEvent::SportUpdated,
                $sport,
                ['slug' => $sport->slug, 'changes' => $changes],
                null,
                null,
            );

            return $sport;
        });
    }
}
