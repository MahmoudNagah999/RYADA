<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Domain\Payment\Actions\GetSubscriptionBalanceAction;
use App\Domain\Payment\Actions\ListPaymentsAction;
use App\Domain\Payment\Actions\RecordPaymentAction;
use App\Domain\Payment\Actions\RefundPaymentAction;
use App\Domain\Payment\Actions\VoidPaymentAction;
use App\Domain\Payment\DTOs\PaymentReceiptData;
use App\Domain\Payment\Models\Payment;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use App\Http\Requests\Payment\ListPaymentsRequest;
use App\Http\Requests\Payment\RecordPaymentRequest;
use App\Http\Requests\Payment\RefundPaymentRequest;
use App\Http\Requests\Payment\VoidPaymentRequest;
use App\Http\Resources\PaymentReceiptResource;
use App\Http\Resources\PaymentRefundResource;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PaymentController
{
    public function index(
        ListPaymentsRequest $request,
        int $subscription,
        ListPaymentsAction $action,
    ): AnonymousResourceCollection {
        $model = PlayerSubscription::query()->findOrFail($subscription);

        return PaymentResource::collection($action->execute($model));
    }

    public function store(
        RecordPaymentRequest $request,
        int $subscription,
        RecordPaymentAction $action,
    ): JsonResponse {
        $user = $request->user();
        abort_if($user === null, 401);

        $model = PlayerSubscription::query()->findOrFail($subscription);

        $payment = $action->execute($request->toData($model->id, $user->id));

        return (new PaymentResource($payment))
            ->response()
            ->setStatusCode(201);
    }

    public function receipt(
        Request $request,
        int $payment,
        GetSubscriptionBalanceAction $balanceAction,
    ): PaymentReceiptResource {
        $model = Payment::query()
            ->with(['subscription.player', 'subscription.membershipPlan', 'subscription.club'])
            ->findOrFail($payment);

        Gate::authorize('view', $model);

        return new PaymentReceiptResource(
            new PaymentReceiptData($model, $balanceAction->execute($model->subscription)),
        );
    }

    public function void(
        VoidPaymentRequest $request,
        int $payment,
        VoidPaymentAction $action,
    ): PaymentResource {
        $user = $request->user();
        abort_if($user === null, 401);

        $model = Payment::query()->findOrFail($payment);

        $voided = $action->execute($request->toData($model->id, $user->id));

        return new PaymentResource($voided->load('refunds'));
    }

    public function refund(
        RefundPaymentRequest $request,
        int $payment,
        RefundPaymentAction $action,
    ): JsonResponse {
        $user = $request->user();
        abort_if($user === null, 401);

        $model = Payment::query()->findOrFail($payment);

        $refund = $action->execute($request->toData($model->id, $user->id));

        return (new PaymentRefundResource($refund))
            ->response()
            ->setStatusCode(201);
    }
}
