<?php

namespace App\Domain\Notification\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Pure computations shared by the notifier and its tests: dedup keys and days left.
 * A null-free API keeps them usable outside a booted application.
 */
final class NotificationSchedule
{
    /**
     * One notification per subscription and end date.
     */
    public static function expiringDedupKey(int $subscriptionId, string $endsOn): string
    {
        return sprintf('subscription.expiring:%d:%s', $subscriptionId, $endsOn);
    }

    /**
     * One notification per subscription and ISO week, e.g. `subscription.unpaid:7:2026-W05`.
     */
    public static function unpaidDedupKey(int $subscriptionId, DateTimeInterface $now): string
    {
        return sprintf(
            'subscription.unpaid:%d:%s-W%s',
            $subscriptionId,
            $now->format('o'),
            $now->format('W'),
        );
    }

    /**
     * Whole days between two `Y-m-d` dates (`0` on the same day).
     */
    public static function daysLeft(string $today, string $endsOn): int
    {
        return (int) Carbon::parse($today)->diffInDays(Carbon::parse($endsOn));
    }
}
