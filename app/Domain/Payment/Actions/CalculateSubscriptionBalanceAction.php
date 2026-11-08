<?php

namespace App\Domain\Payment\Actions;

use App\Domain\Payment\DTOs\SubscriptionBalance;
use App\Domain\Payment\Enums\PaymentStatus;

/**
 * Pure balance computation: no database access, so it is unit-testable on primitives.
 */
class CalculateSubscriptionBalanceAction
{
    /**
     * @param  array<int, int>  $paymentAmounts  Amounts of NON-voided payments, in cents.
     * @param  array<int, int>  $refundAmounts  Refunds of those payments, in cents.
     */
    public function execute(
        int $totalCents,
        array $paymentAmounts,
        array $refundAmounts,
        bool $isCancelled,
    ): SubscriptionBalance {
        $paidCents = array_sum($paymentAmounts);
        $refundedCents = array_sum($refundAmounts);
        $netPaidCents = $paidCents - $refundedCents;

        $outstandingCents = $isCancelled
            ? 0
            : max($totalCents - $netPaidCents, 0);

        $status = match (true) {
            $netPaidCents === 0 && $refundedCents > 0 => PaymentStatus::Refunded,
            $netPaidCents === 0 && $totalCents > 0 => PaymentStatus::Unpaid,
            $netPaidCents >= $totalCents => PaymentStatus::Paid,
            default => PaymentStatus::Partial,
        };

        return new SubscriptionBalance(
            totalCents: $totalCents,
            paidCents: $paidCents,
            refundedCents: $refundedCents,
            netPaidCents: $netPaidCents,
            outstandingCents: $outstandingCents,
            status: $status,
        );
    }
}
