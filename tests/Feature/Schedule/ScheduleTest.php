<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

function schScheduleEvent(string $name): ?Event
{
    return collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => $event->description === $name);
}

it('runs the due-subscription notifier daily at 08:00 in the subscriptions timezone', function () {
    $event = schScheduleEvent('club-notify-due');

    expect($event)->not->toBeNull()
        ->and($event->command)->toContain('club:notify-due')
        ->and($event->expression)->toBe('0 8 * * *')
        ->and($event->timezone)->toBe((string) config('subscriptions.timezone'))
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});

it('runs the plan-expiry reminder daily at 08:05 in the subscriptions timezone', function () {
    $event = schScheduleEvent('clubs-notify-plan-expiry');

    expect($event)->not->toBeNull()
        ->and($event->command)->toContain('clubs:notify-plan-expiry')
        ->and($event->expression)->toBe('5 8 * * *')
        ->and($event->timezone)->toBe((string) config('subscriptions.timezone'))
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});

it('keeps the horizon snapshot scheduled every five minutes', function () {
    $snapshots = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'horizon:snapshot'))
        ->values();

    expect($snapshots)->toHaveCount(1)
        ->and($snapshots->first()->expression)->toBe('*/5 * * * *');
});
