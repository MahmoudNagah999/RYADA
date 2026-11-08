<?php

namespace App\Domain\Payment\DTOs;

final readonly class VoidPaymentData
{
    public function __construct(
        public int $paymentId,
        public string $reason,
        public ?int $voidedBy = null,
    ) {}
}
