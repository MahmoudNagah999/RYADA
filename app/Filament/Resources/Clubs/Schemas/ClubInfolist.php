<?php

namespace App\Filament\Resources\Clubs\Schemas;

use App\Domain\Club\Enums\ClubStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ClubInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label(__('admin.clubs.fields.name')),
                TextEntry::make('slug')
                    ->label(__('admin.clubs.fields.slug')),
                TextEntry::make('status')
                    ->label(__('admin.clubs.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (ClubStatus $state): string => __("admin.clubs.status.{$state->value}"))
                    ->color(fn (ClubStatus $state): string => match ($state) {
                        ClubStatus::Active => 'success',
                        ClubStatus::Suspended => 'danger',
                    }),
                TextEntry::make('suspended_at')
                    ->label(__('admin.clubs.fields.suspended_at'))
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('plan.name')
                    ->label(__('admin.clubs.fields.plan'))
                    ->placeholder('—'),
                TextEntry::make('subscription_starts_at')
                    ->label(__('admin.clubs.fields.subscription_starts_at'))
                    ->date()
                    ->placeholder('—'),
                TextEntry::make('subscription_ends_at')
                    ->label(__('admin.clubs.fields.subscription_ends_at'))
                    ->date()
                    ->placeholder('—'),
                TextEntry::make('created_at')
                    ->label(__('admin.clubs.fields.created_at'))
                    ->dateTime(),
            ]);
    }
}
