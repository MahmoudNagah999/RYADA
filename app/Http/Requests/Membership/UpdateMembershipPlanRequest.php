<?php

namespace App\Http\Requests\Membership;

use App\Domain\Membership\Models\MembershipPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateMembershipPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $plan = MembershipPlan::query()->find((int) $this->route('plan'));

        // Unknown (or other-club) plan: let the controller answer 404.
        return $user !== null && ($plan === null || Gate::forUser($user)->allows('update', $plan));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'sport_id' => ['prohibited'],
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'price_cents' => ['required', 'integer', 'min:0', 'max:100000000'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:60'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
