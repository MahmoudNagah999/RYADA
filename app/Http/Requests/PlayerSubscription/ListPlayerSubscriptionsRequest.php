<?php

namespace App\Http\Requests\PlayerSubscription;

use App\Domain\PlayerSubscription\DTOs\ListPlayerSubscriptionsData;
use App\Domain\PlayerSubscription\Enums\SubscriptionStatus;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ListPlayerSubscriptionsRequest extends FormRequest
{
    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 100;

    /**
     * For the nested route an unknown (or other club's) player still passes here; the controller
     * loads the player to answer 404. The policy ability is checked against the model class.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && Gate::forUser($user)->allows('viewAny', PlayerSubscription::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'player_id' => ['nullable', 'integer'],
            'membership_plan_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(SubscriptionStatus::class)],
            'sort' => ['nullable', 'string', Rule::in([
                'starts_on', '-starts_on', 'ends_on', '-ends_on', 'created_at', '-created_at',
            ])],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toData(?int $playerId = null): ListPlayerSubscriptionsData
    {
        $sort = $this->string('sort', '-starts_on')->toString();

        return new ListPlayerSubscriptionsData(
            playerId: $playerId ?? ($this->filled('player_id') ? $this->integer('player_id') : null),
            membershipPlanId: $this->filled('membership_plan_id') ? $this->integer('membership_plan_id') : null,
            status: $this->filled('status') ? SubscriptionStatus::from($this->string('status')->toString()) : null,
            sortColumn: ltrim($sort, '-'),
            sortDirection: str_starts_with($sort, '-') ? 'desc' : 'asc',
            perPage: min($this->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE),
        );
    }
}
