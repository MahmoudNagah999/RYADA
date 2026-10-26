<?php

namespace App\Filament\Resources\Clubs\Actions;

use App\Domain\Club\Actions\ActivateClubAction;
use App\Domain\Club\Actions\SuspendClubAction;
use App\Domain\Club\Models\Club;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ClubStatusActions
{
    public static function suspend(): Action
    {
        return Action::make('suspend')
            ->label(__('admin.clubs.actions.suspend'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('admin.clubs.actions.suspend_confirm'))
            ->visible(fn (Club $record): bool => $record->isActive())
            ->action(function (Club $record): void {
                app(SuspendClubAction::class)->execute($record);

                Notification::make()
                    ->title(__('admin.clubs.notifications.suspended'))
                    ->success()
                    ->send();
            });
    }

    public static function activate(): Action
    {
        return Action::make('activate')
            ->label(__('admin.clubs.actions.activate'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('admin.clubs.actions.activate_confirm'))
            ->visible(fn (Club $record): bool => $record->isSuspended())
            ->action(function (Club $record): void {
                app(ActivateClubAction::class)->execute($record);

                Notification::make()
                    ->title(__('admin.clubs.notifications.activated'))
                    ->success()
                    ->send();
            });
    }
}
