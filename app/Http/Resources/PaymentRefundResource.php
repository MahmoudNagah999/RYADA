<?php

namespace App\Http\Resources;

use App\Domain\Payment\Models\PaymentRefund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentRefund
 */
class PaymentRefundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'refund_number' => $this->refund_number,
            'refund_code' => 'REF-'.str_pad((string) $this->refund_number, 6, '0', STR_PAD_LEFT),
            'amount_cents' => $this->amount_cents,
            'method' => $this->method->value,
            'refunded_on' => $this->refunded_on->toDateString(),
            'reason' => $this->reason,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
