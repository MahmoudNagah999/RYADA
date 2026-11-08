<?php

namespace App\Http\Resources;

use App\Domain\Payment\DTOs\PaymentReceiptData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentReceiptData
 */
class PaymentReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payment = $this->payment;
        $subscription = $payment->subscription;
        $club = $subscription->club;
        $player = $subscription->player;

        return [
            'receipt_code' => 'RCP-'.str_pad((string) $payment->receipt_number, 6, '0', STR_PAD_LEFT),
            'paid_on' => $payment->paid_on->toDateString(),
            'amount_cents' => $payment->amount_cents,
            'method' => $payment->method->value,
            'reference' => $payment->reference,
            'voided' => $payment->voided_at !== null,
            'club' => [
                'name' => $club->name,
                'contact_email' => $club->contact_email,
                'contact_phone' => $club->contact_phone,
                'address' => $club->address,
                'city' => $club->city,
            ],
            'player' => [
                'id' => $player->id,
                'name' => $player->name,
            ],
            'subscription' => [
                'id' => $subscription->id,
                'plan' => $subscription->membershipPlan->name,
                'starts_on' => $subscription->starts_on->toDateString(),
                'ends_on' => $subscription->ends_on->toDateString(),
                'total_cents' => $subscription->total_cents,
            ],
            'balance' => (new SubscriptionBalanceResource($this->balance))->resolve(),
        ];
    }
}
