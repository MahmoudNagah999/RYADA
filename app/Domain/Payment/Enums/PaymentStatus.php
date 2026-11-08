<?php

namespace App\Domain\Payment\Enums;

/**
 * Computed payment status of a subscription. Never stored: it is derived from the
 * subscription total and the non-voided payments and their refunds.
 */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Refunded = 'refunded';
}
