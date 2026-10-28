<?php

namespace App\Filament\Resources\Activities\Tables;

use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Audit\Models\Activity;
use App\Domain\Club\Models\Club;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('admin.audit.fields.when'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('description')
                    ->label(__('admin.audit.fields.event'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : __("admin.audit.events.{$state}"))
                    ->searchable(),
                TextColumn::make('causer_name')
                    ->label(__('admin.audit.fields.causer'))
                    ->state(function (Activity $record): string {
                        $causer = $record->causer;

                        if ($causer === null) {
                            return __('admin.audit.system');
                        }

                        return (string) ($causer->getAttribute('name') ?? $causer->getAttribute('email'));
                    }),
                TextColumn::make('club.name')
                    ->label(__('admin.audit.fields.club'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('subject_label')
                    ->label(__('admin.audit.fields.subject'))
                    ->state(fn (Activity $record): string => $record->subject_type === null
                        ? '—'
                        : class_basename($record->subject_type).' #'.$record->subject_id),
                TextColumn::make('properties')
                    ->label(__('admin.audit.fields.details'))
                    ->state(fn (Activity $record): string => $record->properties->toJson(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                    ->limit(120)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('description')
                    ->label(__('admin.audit.fields.event'))
                    ->options(collect(ActivityEvent::cases())->mapWithKeys(
                        fn (ActivityEvent $event): array => [$event->value => __("admin.audit.events.{$event->value}")]
                    )->all()),
                SelectFilter::make('club_id')
                    ->label(__('admin.audit.fields.club'))
                    ->options(fn (): array => Club::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
            ]);
    }
}
