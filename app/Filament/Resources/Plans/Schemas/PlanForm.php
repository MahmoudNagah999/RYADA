<?php

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name.en')
                    ->label(__('admin.plans.fields.name_en'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('name.ar')
                    ->label(__('admin.plans.fields.name_ar'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label(__('admin.plans.fields.slug'))
                    ->required()
                    ->alphaDash()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('price_cents')
                    ->label(__('admin.plans.fields.price'))
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->formatStateUsing(fn ($state) => $state === null ? null : $state / 100)
                    ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100)),
                TextInput::make('duration_months')
                    ->label(__('admin.plans.fields.duration_months'))
                    ->numeric()
                    ->integer()
                    ->required()
                    ->minValue(1)
                    ->maxValue(120),
                TextInput::make('max_players')
                    ->label(__('admin.plans.fields.max_players'))
                    ->helperText(__('admin.plans.unlimited_hint'))
                    ->numeric()
                    ->integer()
                    ->minValue(1),
                TextInput::make('max_coaches')
                    ->label(__('admin.plans.fields.max_coaches'))
                    ->helperText(__('admin.plans.unlimited_hint'))
                    ->numeric()
                    ->integer()
                    ->minValue(1),
                Toggle::make('is_active')
                    ->label(__('admin.plans.fields.is_active'))
                    ->default(true),
            ]);
    }
}
