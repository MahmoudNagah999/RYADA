<?php

namespace App\Domain\Notification\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Pure computations shared by the notifiers and their tests: dedup keys, days left and the
 * reminder thresholds. A null-free API keeps them usable outside a booted application.
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
     * One reminder per club plan, end date and threshold, so renewing the plan (a new end
     * date) re-enables the reminders instead of silencing them for good.
     */
    public static function planExpiringDedupKey(int $clubId, string $endsOn, int $threshold): string
    {
        return sprintf('club.plan_expiring:%d:%s:%d', $clubId, $endsOn, $threshold);
    }

    /**
     * The smallest configured threshold that already applies, so a missed scheduler day
     * delays a reminder instead of losing it. `null` when none applies: the plan is past its
     * end date (`days_left < 0`) or still further away than every threshold.
     *
     * @param  list<int>  $thresholds
     */
    public static function planExpiryThreshold(int $daysLeft, array $thresholds): ?int
    {
        if ($daysLeft < 0) {
            return null;
        }

        $applicable = null;

        foreach ($thresholds as $threshold) {
            if ($threshold >= $daysLeft && ($applicable === null || $threshold < $applicable)) {
                $applicable = $threshold;
            }
        }

        return $applicable;
    }

    /**
     * `NOTIFY_PLAN_EXPIRY_DAYS` ("7,1") to a sorted list of distinct positive days.
     * Anything that is not a positive whole number is ignored ("1, ,x,7" gives `[1, 7]`).
     *
     * @return list<int>
     */
    public static function parseReminderDays(string $raw): array
    {
        $days = [];

        foreach (explode(',', $raw) as $part) {
            $part = trim($part);

            if (! ctype_digit($part)) {
                continue;
            }

            $day = (int) $part;

            if ($day > 0) {
                $days[] = $day;
            }
        }

        $days = array_values(array_unique($days));

        sort($days);

        return $days;
    }

    /**
     * Whole days between two `Y-m-d` dates (`0` on the same day, negative in the past).
     */
    public static function daysLeft(string $today, string $endsOn): int
    {
        return (int) Carbon::parse($today)->diffInDays(Carbon::parse($endsOn));
    }
}
