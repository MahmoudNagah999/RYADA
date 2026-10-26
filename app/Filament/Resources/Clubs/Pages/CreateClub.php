<?php

namespace App\Filament\Resources\Clubs\Pages;

use App\Domain\Club\Actions\CreateClubAction;
use App\Filament\Resources\Clubs\ClubResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateClub extends CreateRecord
{
    protected static string $resource = ClubResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateClubAction::class)->execute($data['name']);
    }
}
