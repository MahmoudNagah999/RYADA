<?php

namespace App\Domain\Notification\DTOs;

/**
 * How many notifications a notifier run created, per type.
 */
final readonly class NotifyDueSubscriptionsResult
{
    public function __construct(
        public int $expiring,
        public int $unpaid,
    ) {}

    public function total(): int
    {
        return $this->expiring + $this->unpaid;
    }
}
