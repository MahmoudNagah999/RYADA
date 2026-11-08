<?php

namespace App\Domain\Payment\DTOs;

use App\Domain\Payment\Enums\PaymentMethod;

final readonly class RefundPaymentData
{
    /**
     * @param  string|null  $refundedOn  `Y-m-d`, defaults to today in the subscription timezone.
     */
    public function __construct(
        public int $paymentId,
        public int $amountCents,
        public PaymentMethod $method,
        public string $reason,
        public ?string $refundedOn = null,
        public ?int $recordedBy = null,
    ) {}
}
