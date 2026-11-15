<?php

use App\Domain\Notification\Support\NotificationSchedule;

it('builds the plan-expiry dedup key from the club, the end date and the threshold', function () {
    expect(NotificationSchedule::planExpiringDedupKey(4, '2026-06-08', 7))
        ->toBe('club.plan_expiring:4:2026-06-08:7')
        // Another threshold is another reminder, so it gets its own key.
        ->and(NotificationSchedule::planExpiringDedupKey(4, '2026-06-08', 1))
        ->toBe('club.plan_expiring:4:2026-06-08:1')
        // A renewed plan (new end date) re-enables the reminders.
        ->and(NotificationSchedule::planExpiringDedupKey(4, '2027-06-08', 7))
        ->toBe('club.plan_expiring:4:2027-06-08:7');
});

it('picks the smallest threshold that already applies', function () {
    expect(NotificationSchedule::planExpiryThreshold(7, [7, 1]))->toBe(7)
        // A missed scheduler day still sends a reminder, the nearest one that was not sent yet.
        ->and(NotificationSchedule::planExpiryThreshold(6, [7, 1]))->toBe(7)
        ->and(NotificationSchedule::planExpiryThreshold(2, [7, 1]))->toBe(7)
        ->and(NotificationSchedule::planExpiryThreshold(1, [7, 1]))->toBe(1)
        // A club that already got the "7" reminder only gets the "1" one at one day left.
        ->and(NotificationSchedule::planExpiryThreshold(1, [1, 7]))->toBe(1)
        // Ending today: the middleware still lets the club through, so it is reminded.
        ->and(NotificationSchedule::planExpiryThreshold(0, [7, 1]))->toBe(1)
        ->and(NotificationSchedule::planExpiryThreshold(3, [30, 7, 1]))->toBe(7);
});

it('returns no threshold when the plan is further away than every threshold', function () {
    expect(NotificationSchedule::planExpiryThreshold(8, [7, 1]))->toBeNull()
        ->and(NotificationSchedule::planExpiryThreshold(31, [30, 7, 1]))->toBeNull()
        ->and(NotificationSchedule::planExpiryThreshold(2, []))->toBeNull();
});

it('returns no threshold for a plan that is already past its end date', function () {
    expect(NotificationSchedule::planExpiryThreshold(-1, [7, 1]))->toBeNull()
        ->and(NotificationSchedule::planExpiryThreshold(-30, [7, 1]))->toBeNull();
});

it('parses the configured reminder days into a sorted list of positive ints', function () {
    expect(NotificationSchedule::parseReminderDays('7,1'))->toBe([1, 7])
        ->and(NotificationSchedule::parseReminderDays('1, 7'))->toBe([1, 7])
        ->and(NotificationSchedule::parseReminderDays('30,7,3,1'))->toBe([1, 3, 7, 30])
        // Duplicates would send the same reminder twice, so they collapse.
        ->and(NotificationSchedule::parseReminderDays('7,7,1'))->toBe([1, 7])
        ->and(NotificationSchedule::parseReminderDays('7,  ,x,-3,0,1'))->toBe([1, 7]);
});

it('parses garbage into an empty list instead of throwing', function () {
    expect(NotificationSchedule::parseReminderDays('garbage'))->toBe([])
        ->and(NotificationSchedule::parseReminderDays(''))->toBe([])
        ->and(NotificationSchedule::parseReminderDays('  '))->toBe([])
        ->and(NotificationSchedule::parseReminderDays('0,-1'))->toBe([]);
});
