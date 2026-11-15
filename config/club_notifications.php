<?php

use App\Domain\Notification\Support\NotificationSchedule;

return [
    // A subscription ending within this many days from "today" gets an expiring notification.
    'expiring_within_days' => (int) env('NOTIFY_EXPIRING_DAYS', 7),

    // An unpaid subscription is not chased until it started this many days ago or more.
    'unpaid_grace_days' => (int) env('NOTIFY_UNPAID_GRACE_DAYS', 3),

    // Days before a club's SaaS plan ends that trigger a reminder to the owner (ADR-010).
    // Comma separated, sorted, duplicates and junk dropped: "7,1" means "7" then "1" days left.
    'plan_expiry_reminder_days' => NotificationSchedule::parseReminderDays(
        (string) env('NOTIFY_PLAN_EXPIRY_DAYS', '7,1'),
    ),

    // The due list is walked page by page, this many subscriptions at a time.
    'page_size' => 100,
];
