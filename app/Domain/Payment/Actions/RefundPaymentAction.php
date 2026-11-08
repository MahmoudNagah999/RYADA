<?php

namespace App\Domain\Payment\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Payment\DTOs\RefundPaymentData;
use App\Domain\Payment\Enums\DocumentKind;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\PaymentRefund;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RefundPaymentAction
{
    public function __construct(
        private readonly NextDocumentNumberAction $nextDocumentNumber,
        private readonly RecordActivityAction $recordActivity,
    ) {}

    /**
     * Runs in the current club context and locks the payment row. Refunds are allowed on
     * active, expired and cancelled subscriptions, as long as the payment is not voided
     * and the amount does not exceed what is still refundable.
     */
    public function execute(RefundPaymentData $data): PaymentRefund
    {
        return DB::transaction(function () use ($data): PaymentRefund {
            $payment = Payment::query()
                ->whereKey($data->paymentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->isVoided()) {
                throw new BusinessRuleException('payment.voided', [], 422);
            }

            $alreadyRefunded = (int) $payment->refunds()->sum('amount_cents');
            $refundable = $payment->amount_cents - $alreadyRefunded;

            if ($data->amountCents > $refundable) {
                throw new BusinessRuleException(
                    'payment.refund_exceeds_payment',
                    ['refundable' => $refundable],
                    422,
                );
            }

            $today = $this->today();
            $refundedOn = $data->refundedOn ?? $today;

            if ($refundedOn > $today) {
                throw new BusinessRuleException('payment.date_in_future', [], 422);
            }

            $refund = PaymentRefund::query()->create([
                'payment_id' => $payment->id,
                'refund_number' => $this->nextDocumentNumber->execute(DocumentKind::Refund),
                'amount_cents' => $data->amountCents,
                'method' => $data->method,
                'refunded_on' => $refundedOn,
                'reason' => $data->reason,
                'recorded_by' => $data->recordedBy,
            ]);

            $this->recordActivity->execute(
                ActivityEvent::PaymentRefunded,
                $refund,
                [
                    'refund_id' => $refund->id,
                    'payment_id' => $payment->id,
                    'subscription_id' => $payment->player_subscription_id,
                    'refund_number' => $refund->refund_number,
                    'amount_cents' => $refund->amount_cents,
                    'method' => $refund->method->value,
                ],
                null,
                null,
            );

            return $refund;
        });
    }

    private function today(): string
    {
        return Carbon::now(config('subscriptions.timezone'))->startOfDay()->toDateString();
    }
}
