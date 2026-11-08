<?php

namespace App\Http\Requests\PlayerSubscription;

use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CancelPlayerSubscriptionRequest extends FormRequest
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

        return $subscription === null || Gate::forUser($user)->allows('cancel', $subscription);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
