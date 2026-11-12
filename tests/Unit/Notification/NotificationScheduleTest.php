<?php

use App\Domain\Notification\Support\NotificationSchedule;
use Illuminate\Support\Carbon;

it('builds the expiring dedup key from the subscription and its end date', function () {
    expect(NotificationSchedule::expiringDedupKey(12, '2026-06-05'))
        ->toBe('subscription.expiring:12:2026-06-05');
});

it('builds the unpaid dedup key with the iso year and week', function () {
    expect(NotificationSchedule::unpaidDedupKey(7, Carbon::parse('2026-06-01')))
        ->toBe('subscription.unpaid:7:2026-W23')
        // 2025-12-29 is the Monday of ISO week 1 of 2026, so the ISO year differs.
        ->and(NotificationSchedule::unpaidDedupKey(7, Carbon::parse('2025-12-29')))
        ->toBe('subscription.unpaid:7:2026-W01')
        ->and(NotificationSchedule::unpaidDedupKey(7, Carbon::parse('2026-06-08')))
        ->toBe('subscription.unpaid:7:2026-W24');
});

it('counts the whole days left until the end date', function () {
    expect(NotificationSchedule::daysLeft('2026-06-01', '2026-06-05'))->toBe(4)
        ->and(NotificationSchedule::daysLeft('2026-06-01', '2026-06-02'))->toBe(1)
        ->and(NotificationSchedule::daysLeft('2026-06-01', '2026-06-01'))->toBe(0);
});
