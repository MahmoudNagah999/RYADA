<?php

namespace App\Filament\Resources\Sports\Pages;

use App\Domain\Sport\Actions\UpdateSportAction;
use App\Domain\Sport\DTOs\SportData;
use App\Domain\Sport\Models\Sport;
use App\Filament\Resources\Sports\SportResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSport extends EditRecord
{
    protected static string $resource = SportResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Sport $sport */
        $sport = $this->getRecord();

        // Spatie returns the current-locale string; the form needs both languages.
        $data['name'] = $sport->getTranslations('name');

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Sport $record */
        $data['slug'] = $record->slug;

        return app(UpdateSportAction::class)->execute($record, SportData::fromArray($data));
    }
}
