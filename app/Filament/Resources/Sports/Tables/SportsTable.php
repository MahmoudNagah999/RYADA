<?php

namespace App\Filament\Resources\Sports\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.sports.fields.name'))
                    ->searchable(query: fn ($query, string $search) => $query
                        ->where('name->en', 'like', "%{$search}%")
                        ->orWhere('name->ar', 'like', "%{$search}%")),
                TextColumn::make('slug')
                    ->label(__('admin.sports.fields.slug'))
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label(__('admin.sports.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('admin.sports.fields.is_active')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('slug');
    }
}
