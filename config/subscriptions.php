<?php

return [
    // The timezone used to decide what "today" is when subscribing, renewing or cancelling.
    // APP_TIMEZONE stays UTC (ADR-003: the API returns UTC).
    'timezone' => env('SUBSCRIPTIONS_TIMEZONE', 'Africa/Cairo'),
];
