<?php

namespace App\Http\Requests\Payment;

use App\Domain\Payment\DTOs\RecordPaymentData;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Models\Payment;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    /**
     * For an unknown (or other club's) subscription this returns true so the controller
     * answers 404. The subscription is scoped by the tenant middleware.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $subscription = PlayerSubscription::query()->find((int) $this->route('subscription'));

        return $subscription === null || Gate::forUser($user)->allows('create', Payment::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount_cents' => ['required', 'integer', 'min:1', 'max:100000000'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'paid_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function toData(int $subscriptionId, ?int $recordedBy): RecordPaymentData
    {
        return new RecordPaymentData(
            subscriptionId: $subscriptionId,
            amountCents: $this->integer('amount_cents'),
            method: PaymentMethod::from($this->string('method')->toString()),
            paidOn: $this->filled('paid_on') ? $this->string('paid_on')->toString() : null,
            reference: $this->filled('reference') ? $this->string('reference')->toString() : null,
            note: $this->filled('note') ? $this->string('note')->toString() : null,
            recordedBy: $recordedBy,
        );
    }
}
