<?php

namespace App\Domain\Payment\DTOs;

use App\Domain\Payment\Enums\PaymentMethod;

final readonly class RecordPaymentData
{
    /**
     * @param  string|null  $paidOn  `Y-m-d`, defaults to today in the subscription timezone.
     */
    public function __construct(
        public int $subscriptionId,
        public int $amountCents,
        public PaymentMethod $method,
        public ?string $paidOn = null,
        public ?string $reference = null,
        public ?string $note = null,
        public ?int $recordedBy = null,
    ) {}
}
