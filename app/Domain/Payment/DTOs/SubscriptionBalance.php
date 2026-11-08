<?php

namespace App\Domain\Payment\DTOs;

use App\Domain\Payment\Enums\PaymentStatus;

/**
 * Pure balance breakdown for one subscription. All amounts are integer cents.
 */
final readonly class SubscriptionBalance
{
    public function __construct(
        public int $totalCents,
        public int $paidCents,
        public int $refundedCents,
        public int $netPaidCents,
        public int $outstandingCents,
        public PaymentStatus $status,
    ) {}
}
