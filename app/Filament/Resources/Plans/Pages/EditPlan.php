<?php

namespace App\Filament\Resources\Plans\Pages;

use App\Domain\Subscription\Models\Plan;
use App\Filament\Resources\Plans\PlanResource;
use Filament\Resources\Pages\EditRecord;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Plan $plan */
        $plan = $this->getRecord();

        $data['name'] = $plan->getTranslations('name');

        return $data;
    }
}
