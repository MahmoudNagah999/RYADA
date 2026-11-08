<?php

namespace App\Http\Requests\PlayerSubscription;

use App\Domain\Membership\DTOs\MembershipDiscount;
use App\Domain\Membership\Enums\DiscountType;
use App\Domain\Player\Models\Player;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StorePlayerSubscriptionRequest extends FormRequest
{
    /**
     * For an unknown (or other club's) player this returns true so the controller answers 404.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $player = Player::query()->find((int) $this->route('player'));

        return $player === null || Gate::forUser($user)->allows('create', PlayerSubscription::class);
    }

    /**
     * `exists` is not tenant-scoped, so the plan is limited to the caller's club.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'membership_plan_id' => [
                'required',
                'integer',
                Rule::exists('membership_plans', 'id')->where('club_id', $this->user()?->club_id),
            ],
            'starts_on' => ['sometimes', 'date_format:Y-m-d'],
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
