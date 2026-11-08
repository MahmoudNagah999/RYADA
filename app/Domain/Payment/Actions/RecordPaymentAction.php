<?php

namespace App\Domain\Payment\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Payment\DTOs\RecordPaymentData;
use App\Domain\Payment\Enums\DocumentKind;
use App\Domain\Payment\Models\Payment;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecordPaymentAction
{
    public function __construct(
        private readonly GetSubscriptionBalanceAction $getBalance,
        private readonly NextDocumentNumberAction $nextDocumentNumber,
        private readonly RecordActivityAction $recordActivity,
    ) {}

    /**
     * Runs in the current club context and locks the subscription row, so two concurrent
     * payments cannot both pass the outstanding check. Expired subscriptions may still
     * receive payments (settling debt); cancelled ones may not.
     */
    public function execute(RecordPaymentData $data): Payment
    {
        return DB::transaction(function () use ($data): Payment {
            $subscription = PlayerSubscription::query()
                ->whereKey($data->subscriptionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($subscription->isCancelled()) {
                throw new BusinessRuleException('payment.subscription_cancelled', [], 422);
            }

            $balance = $this->getBalance->execute($subscription);

            if ($data->amountCents > $balance->outstandingCents) {
                throw new BusinessRuleException(
                    'payment.exceeds_outstanding',
                    ['outstanding' => $balance->outstandingCents],
                    422,
                );
            }

            $today = $this->today();
            $paidOn = $data->paidOn ?? $today;

            if ($paidOn > $today) {
                throw new BusinessRuleException('payment.date_in_future', [], 422);
            }

            $payment = Payment::query()->create([
                'player_subscription_id' => $subscription->id,
                'receipt_number' => $this->nextDocumentNumber->execute(DocumentKind::Receipt),
                'amount_cents' => $data->amountCents,
                'method' => $data->method,
                'paid_on' => $paidOn,
                'reference' => $data->reference,
                'note' => $data->note,
                'recorded_by' => $data->recordedBy,
            ]);

            $this->recordActivity->execute(
                ActivityEvent::PaymentRecorded,
                $payment,
                [
                    'payment_id' => $payment->id,
                    'subscription_id' => $subscription->id,
                    'receipt_number' => $payment->receipt_number,
                    'amount_cents' => $payment->amount_cents,
                    'method' => $payment->method->value,
                ],
                null,
                null,
            );

            return $payment;
        });
    }

    private function today(): string
    {
        return Carbon::now(config('subscriptions.timezone'))->startOfDay()->toDateString();
    }
}
