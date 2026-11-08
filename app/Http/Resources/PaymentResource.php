<?php

namespace App\Http\Resources;

use App\Domain\Payment\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $refunds = $this->relationLoaded('refunds') ? $this->refunds : null;

        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'receipt_code' => 'RCP-'.str_pad((string) $this->receipt_number, 6, '0', STR_PAD_LEFT),
            'player_subscription_id' => $this->player_subscription_id,
            'amount_cents' => $this->amount_cents,
            'method' => $this->method->value,
            'paid_on' => $this->paid_on->toDateString(),
            'reference' => $this->reference,
            'note' => $this->note,
            'voided' => $this->voided_at !== null,
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_reason' => $this->void_reason,
            'refunded_cents' => $refunds === null ? 0 : (int) $refunds->sum('amount_cents'),
            'refunds' => PaymentRefundResource::collection($this->whenLoaded('refunds')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
