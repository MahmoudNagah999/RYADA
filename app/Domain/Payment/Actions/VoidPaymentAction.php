<?php

namespace App\Domain\Payment\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Payment\DTOs\VoidPaymentData;
use App\Domain\Payment\Models\Payment;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VoidPaymentAction
{
    public function __construct(private readonly RecordActivityAction $recordActivity) {}

    /**
     * Runs in the current club context. A payment is never updated or deleted: it is
     * voided once, and only while it has no refunds.
     */
    public function execute(VoidPaymentData $data): Payment
    {
        return DB::transaction(function () use ($data): Payment {
            $payment = Payment::query()
                ->whereKey($data->paymentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->isVoided()) {
                throw new BusinessRuleException('payment.already_voided', [], 422);
            }

            if ($payment->refunds()->exists()) {
                throw new BusinessRuleException('payment.void_has_refunds', [], 422);
            }

            $payment->update([
                'voided_at' => Carbon::now(),
                'voided_by' => $data->voidedBy,
                'void_reason' => $data->reason,
            ]);

            $this->recordActivity->execute(
                ActivityEvent::PaymentVoided,
                $payment,
                [
                    'payment_id' => $payment->id,
                    'subscription_id' => $payment->player_subscription_id,
                    'receipt_number' => $payment->receipt_number,
                    'amount_cents' => $payment->amount_cents,
                    'voided_by' => $data->voidedBy,
                ],
                null,
                null,
            );

            return $payment;
        });
    }
}
