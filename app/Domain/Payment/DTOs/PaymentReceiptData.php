<?php

namespace App\Domain\Payment\DTOs;

use App\Domain\Payment\Models\Payment;

/**
 * Receipt payload: the payment (with its relations loaded) and the current balance.
 */
final readonly class PaymentReceiptData
{
    public function __construct(
        public Payment $payment,
        public SubscriptionBalance $balance,
    ) {}
}
