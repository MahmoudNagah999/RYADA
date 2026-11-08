<?php

namespace App\Domain\PlayerSubscription\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /**
     * Pure status computation. Dates are `Y-m-d` strings.
     *
     * - cancelled when cancelled_at is set;
     * - expired when today >= ends_on (ends_on is exclusive);
     * - active otherwise (a subscription that has not started yet counts as active).
     */
    public static function forDate(?string $cancelledAt, string $endsOn, string $today): self
    {
        if ($cancelledAt !== null) {
            return self::Cancelled;
        }

        return $today >= $endsOn ? self::Expired : self::Active;
    }
}
