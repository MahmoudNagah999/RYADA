<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Reminders run at a fixed hour of the club business day (never in UTC), never twice at the same
// time and on a single server: the mutex lives in the shared cache store (ADR-010).
Schedule::command('club:notify-due')
    ->dailyAt('08:00')
    ->timezone((string) config('subscriptions.timezone'))
    ->withoutOverlapping()
    ->onOneServer()
    ->name('club-notify-due');

Schedule::command('clubs:notify-plan-expiry')
    ->dailyAt('08:05')
    ->timezone((string) config('subscriptions.timezone'))
    ->withoutOverlapping()
    ->onOneServer()
    ->name('clubs-notify-plan-expiry');
