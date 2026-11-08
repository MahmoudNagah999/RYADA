<?php

namespace App\Http\Resources;

use App\Domain\Payment\DTOs\SubscriptionBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionBalance
 */
class SubscriptionBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_cents' => $this->totalCents,
            'paid_cents' => $this->paidCents,
            'refunded_cents' => $this->refundedCents,
            'net_paid_cents' => $this->netPaidCents,
            'outstanding_cents' => $this->outstandingCents,
            'status' => $this->status->value,
        ];
    }
}
