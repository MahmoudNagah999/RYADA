<?php

namespace App\Domain\Payment\Actions;

use App\Domain\Payment\DTOs\SubscriptionBalance;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\PaymentRefund;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;

class GetSubscriptionBalanceAction
{
    public function __construct(private readonly CalculateSubscriptionBalanceAction $calculate) {}

    /**
     * Runs in the current club context. Sums only non-voided payments and the refunds
     * recorded against them, then delegates the pure computation.
     */
    public function execute(PlayerSubscription $subscription): SubscriptionBalance
    {
        $paidCents = (int) Payment::query()
            ->where('player_subscription_id', $subscription->id)
            ->whereNull('voided_at')
            ->sum('amount_cents');

        $refundedCents = (int) PaymentRefund::query()
            ->whereIn('payment_id', Payment::query()
                ->select('id')
                ->where('player_subscription_id', $subscription->id)
                ->whereNull('voided_at'))
            ->sum('amount_cents');

        return $this->calculate->execute(
            totalCents: $subscription->total_cents,
            paymentAmounts: [$paidCents],
            refundAmounts: [$refundedCents],
            isCancelled: $subscription->isCancelled(),
        );
    }
}
