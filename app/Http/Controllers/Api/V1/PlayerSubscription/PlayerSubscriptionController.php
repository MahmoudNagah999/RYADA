<?php

namespace App\Http\Controllers\Api\V1\PlayerSubscription;

use App\Domain\Player\Models\Player;
use App\Domain\PlayerSubscription\Actions\CancelPlayerSubscriptionAction;
use App\Domain\PlayerSubscription\Actions\ListPlayerSubscriptionsAction;
use App\Domain\PlayerSubscription\Actions\RenewPlayerSubscriptionAction;
use App\Domain\PlayerSubscription\Actions\SubscribePlayerAction;
use App\Domain\PlayerSubscription\DTOs\CancelPlayerSubscriptionData;
use App\Domain\PlayerSubscription\DTOs\RenewPlayerSubscriptionData;
use App\Domain\PlayerSubscription\DTOs\SubscribePlayerData;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use App\Http\Requests\PlayerSubscription\CancelPlayerSubscriptionRequest;
use App\Http\Requests\PlayerSubscription\ListPlayerSubscriptionsRequest;
use App\Http\Requests\PlayerSubscription\RenewPlayerSubscriptionRequest;
use App\Http\Requests\PlayerSubscription\StorePlayerSubscriptionRequest;
use App\Http\Resources\PlayerSubscriptionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PlayerSubscriptionController
{
    public function indexForPlayer(
        ListPlayerSubscriptionsRequest $request,
        int $player,
        ListPlayerSubscriptionsAction $action,
    ): AnonymousResourceCollection {
        $model = Player::query()->findOrFail($player);

        return PlayerSubscriptionResource::collection($action->execute($request->toData($model->id)));
    }

    public function store(
        StorePlayerSubscriptionRequest $request,
        int $player,
        SubscribePlayerAction $action,
    ): JsonResponse {
        $model = Player::query()->findOrFail($player);

        $subscription = $action->execute(new SubscribePlayerData(
            playerId: $model->id,
            membershipPlanId: $request->integer('membership_plan_id'),
            startsOn: $request->filled('starts_on') ? $request->string('starts_on')->toString() : null,
            discount: $request->toDiscount(),
        ));

        return (new PlayerSubscriptionResource($subscription->load('membershipPlan.sport')))
            ->response()
            ->setStatusCode(201);
    }

    public function index(
        ListPlayerSubscriptionsRequest $request,
        ListPlayerSubscriptionsAction $action,
    ): AnonymousResourceCollection {
        return PlayerSubscriptionResource::collection($action->execute($request->toData()));
    }

    public function show(Request $request, int $subscription): PlayerSubscriptionResource
    {
        $model = PlayerSubscription::query()->with('membershipPlan.sport')->findOrFail($subscription);

        Gate::authorize('view', $model);

        return new PlayerSubscriptionResource($model);
    }

    public function renew(
        RenewPlayerSubscriptionRequest $request,
        int $subscription,
        RenewPlayerSubscriptionAction $action,
    ): JsonResponse {
        $model = PlayerSubscription::query()->findOrFail($subscription);

        $renewed = $action->execute($model, new RenewPlayerSubscriptionData($request->toDiscount()));

        return (new PlayerSubscriptionResource($renewed->load('membershipPlan.sport')))
            ->response()
            ->setStatusCode(201);
    }

    public function cancel(
        CancelPlayerSubscriptionRequest $request,
        int $subscription,
        CancelPlayerSubscriptionAction $action,
    ): PlayerSubscriptionResource {
        $user = $request->user();
        abort_if($user === null, 401);

        $model = PlayerSubscription::query()->findOrFail($subscription);

        $cancelled = $action->execute($model, new CancelPlayerSubscriptionData($user->id));

        return new PlayerSubscriptionResource($cancelled->load('membershipPlan.sport'));
    }
}
