<?php

namespace App\Filament\Resources\Plans\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.plans.fields.name'))
                    ->searchable(),
                TextColumn::make('slug')
                    ->label(__('admin.plans.fields.slug')),
                TextColumn::make('price_cents')
                    ->label(__('admin.plans.fields.price'))
                    ->formatStateUsing(fn (int $state): string => number_format($state / 100, 2))
                    ->sortable(),
                TextColumn::make('duration_months')
                    ->label(__('admin.plans.fields.duration_months'))
                    ->sortable(),
                TextColumn::make('max_players')
                    ->label(__('admin.plans.fields.max_players'))
                    ->placeholder(__('admin.plans.unlimited')),
                TextColumn::make('max_coaches')
                    ->label(__('admin.plans.fields.max_coaches'))
                    ->placeholder(__('admin.plans.unlimited')),
                IconColumn::make('is_active')
                    ->label(__('admin.plans.fields.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('price_cents')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
