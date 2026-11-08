<?php

namespace App\Http\Resources;

use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin PlayerSubscription
 */
class PlayerSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $today = Carbon::now(config('subscriptions.timezone'))->startOfDay()->toDateString();

        return [
            'id' => $this->id,
            'player_id' => $this->player_id,
            'membership_plan' => $this->whenLoaded('membershipPlan', fn () => [
                'id' => $this->membershipPlan->id,
                'name' => $this->membershipPlan->name,
                'sport_id' => $this->membershipPlan->sport_id,
            ]),
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'status' => $this->statusOn($today)->value,
            'price_cents' => $this->price_cents,
            'currency' => $this->currency,
            'duration_months' => $this->duration_months,
            'discount' => $this->discount_type === null ? null : [
                'type' => $this->discount_type->value,
                'value' => $this->discount_value,
                'cents' => $this->discount_cents,
            ],
            'total_cents' => $this->total_cents,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'renewed_from_id' => $this->renewed_from_id,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
