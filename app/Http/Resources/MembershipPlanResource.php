<?php

namespace App\Http\Resources;

use App\Domain\Membership\Models\MembershipPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MembershipPlan
 */
class MembershipPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sport_id' => $this->sport_id,
            'sport' => $this->whenLoaded('sport', fn () => [
                'slug' => $this->sport->slug,
                'name' => $this->sport->name,
            ]),
            'name' => $this->name,
            'price_cents' => $this->price_cents,
            'currency' => $this->currency,
            'duration_months' => $this->duration_months,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
