<?php

namespace App\Domain\Payment\Actions;

use App\Domain\Payment\Models\Payment;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use Illuminate\Database\Eloquent\Collection;

class ListPaymentsAction
{
    /**
     * Runs in the current club context. Newest first, voided payments included, and
     * refunds eager loaded so the resource never triggers an N+1.
     *
     * @return Collection<int, Payment>
     */
    public function execute(PlayerSubscription $subscription): Collection
    {
        return Payment::query()
            ->where('player_subscription_id', $subscription->id)
            ->with('refunds')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }
}
