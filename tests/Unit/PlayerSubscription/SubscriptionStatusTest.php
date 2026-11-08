<?php

use App\Domain\PlayerSubscription\Enums\SubscriptionStatus;

it('is cancelled as soon as cancelled_at is set, whatever the dates', function () {
    expect(SubscriptionStatus::forDate('2026-01-01 10:00:00', '2026-02-01', '2026-01-15'))
        ->toBe(SubscriptionStatus::Cancelled)
        ->and(SubscriptionStatus::forDate('2026-01-01 10:00:00', '2026-02-01', '2026-02-01'))
        ->toBe(SubscriptionStatus::Cancelled);
});

it('is expired when today is on or after ends_on (ends_on is exclusive)', function () {
    expect(SubscriptionStatus::forDate(null, '2026-02-01', '2026-02-01'))
        ->toBe(SubscriptionStatus::Expired)
        ->and(SubscriptionStatus::forDate(null, '2026-02-01', '2026-02-02'))
        ->toBe(SubscriptionStatus::Expired);
});

it('is active before ends_on, including before the subscription starts', function () {
    expect(SubscriptionStatus::forDate(null, '2026-02-01', '2026-01-31'))
        ->toBe(SubscriptionStatus::Active)
        ->and(SubscriptionStatus::forDate(null, '2026-02-01', '2025-12-01'))
        ->toBe(SubscriptionStatus::Active);
});
