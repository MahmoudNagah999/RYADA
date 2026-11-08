<?php

namespace App\Http\Requests\Payment;

use App\Domain\Payment\DTOs\VoidPaymentData;
use App\Domain\Payment\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class VoidPaymentRequest extends FormRequest
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

        return $payment === null || Gate::forUser($user)->allows('void', $payment);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function toData(int $paymentId, ?int $voidedBy): VoidPaymentData
    {
        return new VoidPaymentData(
            paymentId: $paymentId,
            reason: $this->string('reason')->toString(),
            voidedBy: $voidedBy,
        );
    }
}
