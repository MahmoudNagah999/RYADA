<?php

namespace App\Http\Resources;

use App\Domain\Payment\DTOs\DueSubscriptionData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DueSubscriptionData
 */
class DueSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subscription = $this->subscription;

        return [
            'id' => $subscription->id,
            'player' => [
                'id' => $subscription->player->id,
                'name' => $subscription->player->name,
            ],
            'membership_plan' => [
                'id' => $subscription->membershipPlan->id,
                'name' => $subscription->membershipPlan->name,
            ],
            'starts_on' => $subscription->starts_on->toDateString(),
            'ends_on' => $subscription->ends_on->toDateString(),
            'total_cents' => $subscription->total_cents,
            'paid_cents' => $this->balance->paidCents,
            'refunded_cents' => $this->balance->refundedCents,
            'outstanding_cents' => $this->balance->outstandingCents,
            'payment_status' => $this->balance->status->value,
        ];
    }
}
