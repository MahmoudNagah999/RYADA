<?php

namespace App\Filament\Resources\Plans\Pages;

use App\Domain\Subscription\Actions\UpdatePlanAction;
use App\Domain\Subscription\DTOs\PlanData;
use App\Domain\Subscription\Models\Plan;
use App\Filament\Resources\Plans\PlanResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Plan $record */
        return app(UpdatePlanAction::class)->execute($record, PlanData::fromArray($data));
    }
}
