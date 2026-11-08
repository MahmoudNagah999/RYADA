<?php

namespace App\Http\Requests\Payment;

use App\Domain\Payment\DTOs\RefundPaymentData;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RefundPaymentRequest extends FormRequest
{
    /**
     * For an unknown (or other club's) payment this returns true so the controller
     * answers 404.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $payment = Payment::query()->find((int) $this->route('payment'));

        return $payment === null || Gate::forUser($user)->allows('refund', $payment);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount_cents' => ['required', 'integer', 'min:1', 'max:100000000'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'refunded_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function toData(int $paymentId, ?int $recordedBy): RefundPaymentData
    {
        return new RefundPaymentData(
            paymentId: $paymentId,
            amountCents: $this->integer('amount_cents'),
            method: PaymentMethod::from($this->string('method')->toString()),
            reason: $this->string('reason')->toString(),
            refundedOn: $this->filled('refunded_on') ? $this->string('refunded_on')->toString() : null,
            recordedBy: $recordedBy,
        );
    }
}
