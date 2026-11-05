<?php

namespace App\Filament\Resources\Sports\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('slug')
                ->label(__('admin.sports.fields.slug'))
                ->required()
                ->maxLength(64)
                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                ->unique(ignoreRecord: true)
                ->disabledOn('edit'),
            TextInput::make('name.en')
                ->label(__('admin.sports.fields.name_en'))
                ->required()
                ->maxLength(100),
            TextInput::make('name.ar')
                ->label(__('admin.sports.fields.name_ar'))
                ->required()
                ->maxLength(100),
            Toggle::make('is_active')
                ->label(__('admin.sports.fields.is_active'))
                ->default(true),
        ]);
    }
}
