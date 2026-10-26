<?php

namespace App\Filament\Resources\Clubs\Actions;

use App\Domain\Club\Models\Club;
use App\Domain\Subscription\Actions\AssignPlanToClubAction;
use App\Domain\Subscription\Models\Plan;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class ClubPlanActions
{
    public static function assign(): Action
    {
        return Action::make('assignPlan')
            ->label(__('admin.clubs.actions.assign_plan'))
            ->icon(Heroicon::OutlinedCreditCard)
            ->schema([
                Select::make('plan_id')
                    ->label(__('admin.clubs.fields.plan'))
                    ->options(fn (): array => Plan::query()
                        ->where('is_active', true)
                        ->orderBy('price_cents')
                        ->get()
                        ->mapWithKeys(fn (Plan $plan): array => [$plan->id => (string) $plan->name])
                        ->all())
                    ->required(),
                DatePicker::make('starts_at')
                    ->label(__('admin.clubs.fields.subscription_starts_at'))
                    ->default(now())
                    ->required(),
            ])
            ->action(function (Club $record, array $data): void {
                app(AssignPlanToClubAction::class)->execute(
                    $record,
                    Plan::query()->findOrFail($data['plan_id']),
                    Carbon::parse($data['starts_at']),
                );

                Notification::make()
                    ->title(__('admin.clubs.notifications.plan_assigned'))
                    ->success()
                    ->send();
            });
    }
}
