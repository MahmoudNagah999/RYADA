<?php

return [
    // The timezone used to decide what "today" is when recording attendance.
    // APP_TIMEZONE stays UTC (ADR-003: the API returns UTC).
    'timezone' => env('ATTENDANCE_TIMEZONE', 'Africa/Cairo'),
];
