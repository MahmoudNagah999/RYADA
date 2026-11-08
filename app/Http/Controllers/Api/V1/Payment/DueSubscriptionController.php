<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Domain\Payment\Actions\ListDueSubscriptionsAction;
use App\Http\Requests\Payment\ListDueSubscriptionsRequest;
use App\Http\Resources\DueSubscriptionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DueSubscriptionController
{
    public function index(
        ListDueSubscriptionsRequest $request,
        ListDueSubscriptionsAction $action,
    ): AnonymousResourceCollection {
        return DueSubscriptionResource::collection($action->execute($request->toData()));
    }
}
