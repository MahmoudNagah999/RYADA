<?php

namespace App\Domain\PlayerSubscription\Support;

final class SubscriptionOverlap
{
    /**
     * Half-open interval overlap on `Y-m-d` strings: [starts, ends).
     * Two periods are back-to-back (not overlapping) when one ends exactly when the other starts.
     */
    public static function overlaps(
        string $existingStartsOn,
        string $existingEndsOn,
        string $newStartsOn,
        string $newEndsOn,
    ): bool {
        return $existingStartsOn < $newEndsOn && $existingEndsOn > $newStartsOn;
    }
}
