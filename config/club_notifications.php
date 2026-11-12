<?php

return [
    // A subscription ending within this many days from "today" gets an expiring notification.
    'expiring_within_days' => (int) env('NOTIFY_EXPIRING_DAYS', 7),

    // An unpaid subscription is not chased until it started this many days ago or more.
    'unpaid_grace_days' => (int) env('NOTIFY_UNPAID_GRACE_DAYS', 3),

    // The due list is walked page by page, this many subscriptions at a time.
    'page_size' => 100,
];
