<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Domain\Payment\Actions\GetSubscriptionBalanceAction;
use App\Domain\Payment\Models\Payment;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use App\Http\Resources\SubscriptionBalanceResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SubscriptionBalanceController
{
    public function show(
        Request $request,
        int $subscription,
        GetSubscriptionBalanceAction $action,
    ): SubscriptionBalanceResource {
        $model = PlayerSubscription::query()->findOrFail($subscription);

        Gate::authorize('viewAny', Payment::class);

        return new SubscriptionBalanceResource($action->execute($model));
    }
}
