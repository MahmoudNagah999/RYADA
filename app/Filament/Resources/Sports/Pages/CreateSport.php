<?php

namespace App\Filament\Resources\Sports\Pages;

use App\Domain\Sport\Actions\CreateSportAction;
use App\Domain\Sport\DTOs\SportData;
use App\Filament\Resources\Sports\SportResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSport extends CreateRecord
{
    protected static string $resource = SportResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateSportAction::class)->execute(SportData::fromArray($data));
    }
}
