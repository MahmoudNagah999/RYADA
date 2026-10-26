<?php

namespace App\Filament\Resources\Clubs\Tables;

use App\Domain\Club\Enums\ClubStatus;
use App\Filament\Resources\Clubs\Actions\ClubStatusActions;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ClubsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.clubs.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('admin.clubs.fields.slug'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('admin.clubs.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (ClubStatus $state): string => __("admin.clubs.status.{$state->value}"))
                    ->color(fn (ClubStatus $state): string => match ($state) {
                        ClubStatus::Active => 'success',
                        ClubStatus::Suspended => 'danger',
                    }),
                TextColumn::make('suspended_at')
                    ->label(__('admin.clubs.fields.suspended_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('admin.clubs.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.clubs.fields.status'))
                    ->options(collect(ClubStatus::cases())->mapWithKeys(
                        fn (ClubStatus $status): array => [$status->value => __("admin.clubs.status.{$status->value}")]
                    )->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ClubStatusActions::suspend(),
                ClubStatusActions::activate(),
            ]);
    }
}
