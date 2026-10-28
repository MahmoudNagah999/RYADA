<?php

namespace App\Filament\Resources\Plans\Pages;

use App\Domain\Subscription\Actions\CreatePlanAction;
use App\Domain\Subscription\DTOs\PlanData;
use App\Filament\Resources\Plans\PlanResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreatePlanAction::class)->execute(PlanData::fromArray($data));
    }
}
