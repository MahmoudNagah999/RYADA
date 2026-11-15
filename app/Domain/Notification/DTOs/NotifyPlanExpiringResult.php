<?php

namespace App\Domain\Notification\DTOs;

use App\Domain\Notification\Enums\PlanExpirySkipReason;

/**
 * What one plan-expiry run did: how many notifications it created, or why it created none.
 */
final readonly class NotifyPlanExpiringResult
{
    private function __construct(
        public int $created,
        public ?PlanExpirySkipReason $skipped,
        public ?int $threshold,
    ) {}

    public static function created(int $count, int $threshold): self
    {
        return new self($count, null, $threshold);
    }

    public static function skipped(PlanExpirySkipReason $reason): self
    {
        return new self(0, $reason, null);
    }
}
