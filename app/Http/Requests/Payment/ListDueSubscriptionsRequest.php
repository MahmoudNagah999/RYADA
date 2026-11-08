<?php

namespace App\Http\Requests\Payment;

use App\Domain\Payment\DTOs\ListDueSubscriptionsData;
use App\Domain\Payment\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ListDueSubscriptionsRequest extends FormRequest
{
    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 100;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && Gate::forUser($user)->allows('viewAny', Payment::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', Rule::in(['expiring', 'unpaid', 'all'])],
            'expiring_within_days' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function toData(): ListDueSubscriptionsData
    {
        return new ListDueSubscriptionsData(
            type: $this->string('type', 'all')->toString(),
            expiringWithinDays: $this->integer('expiring_within_days', 7),
            perPage: min($this->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE),
        );
    }
}
