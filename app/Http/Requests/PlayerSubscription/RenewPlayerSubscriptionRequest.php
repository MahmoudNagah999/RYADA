<?php

namespace App\Http\Requests\PlayerSubscription;

use App\Domain\Membership\DTOs\MembershipDiscount;
use App\Domain\Membership\Enums\DiscountType;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RenewPlayerSubscriptionRequest extends FormRequest
{
    /**
     * For an unknown (or other club's) subscription this returns true so the controller answers 404.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $subscription = PlayerSubscription::query()->find((int) $this->route('subscription'));

        return $subscription === null || Gate::forUser($user)->allows('renew', $subscription);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'discount' => ['sometimes', 'nullable', 'array'],
            'discount.type' => ['required_with:discount', 'string', Rule::in(['percent', 'fixed'])],
            'discount.value' => ['required_with:discount', 'integer', 'min:0'],
        ];
    }

    public function toDiscount(): ?MembershipDiscount
    {
        if (! $this->filled('discount.type')) {
            return null;
        }

        $type = DiscountType::from($this->string('discount.type')->toString());
        $value = $this->integer('discount.value');

        return $type === DiscountType::Percent
            ? MembershipDiscount::percent($value)
            : MembershipDiscount::fixed($value);
    }
}
