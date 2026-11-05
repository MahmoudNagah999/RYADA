<?php

namespace App\Domain\Sport\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Sport\DTOs\SportData;
use App\Domain\Sport\Models\Sport;
use Illuminate\Support\Facades\DB;

class CreateSportAction
{
    public function __construct(private readonly RecordActivityAction $recordActivity) {}

    public function execute(SportData $data): Sport
    {
        return DB::transaction(function () use ($data): Sport {
            $sport = Sport::query()->create([
                'slug' => $data->slug,
                'name' => $data->name,
                'is_active' => $data->isActive,
            ]);

            $this->recordActivity->execute(
                ActivityEvent::SportCreated,
                $sport,
                ['slug' => $sport->slug],
                null,
                null,
            );

            return $sport;
        });
    }
}
