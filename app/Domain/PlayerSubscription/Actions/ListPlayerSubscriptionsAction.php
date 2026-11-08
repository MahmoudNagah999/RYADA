<?php

namespace App\Domain\PlayerSubscription\Actions;

use App\Domain\PlayerSubscription\DTOs\ListPlayerSubscriptionsData;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ListPlayerSubscriptionsAction
{
    /**
     * Runs in the current club context (ClubScope limits the rows). The sort column and
     * direction come from a validated whitelist (see ListPlayerSubscriptionsRequest).
     *
     * @return LengthAwarePaginator<int, PlayerSubscription>
     */
    public function execute(ListPlayerSubscriptionsData $data): LengthAwarePaginator
    {
        $today = Carbon::now(config('subscriptions.timezone'))->startOfDay()->toDateString();

        return PlayerSubscription::query()
            ->with('membershipPlan.sport')
            ->when($data->playerId !== null, fn ($query) => $query->where('player_id', $data->playerId))
            ->when($data->membershipPlanId !== null, fn ($query) => $query->where('membership_plan_id', $data->membershipPlanId))
            ->when($data->status !== null, fn ($query) => $query->status($data->status, $today))
            ->orderBy($data->sortColumn, $data->sortDirection)
            ->orderBy('id')
            ->paginate($data->perPage);
    }
}
