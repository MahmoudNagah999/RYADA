<?php

use App\Domain\PlayerSubscription\Support\SubscriptionOverlap;

it('detects overlapping half-open periods', function () {
    expect(SubscriptionOverlap::overlaps('2026-01-01', '2026-02-01', '2026-01-15', '2026-02-15'))
        ->toBeTrue()
        ->and(SubscriptionOverlap::overlaps('2026-01-01', '2026-02-01', '2025-12-15', '2026-01-15'))
        ->toBeTrue();
});

it('treats back-to-back periods as not overlapping', function () {
    expect(SubscriptionOverlap::overlaps('2026-01-01', '2026-02-01', '2026-02-01', '2026-03-01'))
        ->toBeFalse()
        ->and(SubscriptionOverlap::overlaps('2026-01-01', '2026-02-01', '2025-12-01', '2026-01-01'))
        ->toBeFalse();
});

it('treats disjoint periods as not overlapping', function () {
    expect(SubscriptionOverlap::overlaps('2026-01-01', '2026-02-01', '2026-03-01', '2026-04-01'))
        ->toBeFalse();
});
