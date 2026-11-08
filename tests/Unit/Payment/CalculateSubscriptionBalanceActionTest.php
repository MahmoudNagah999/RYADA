<?php

use App\Domain\Payment\Actions\CalculateSubscriptionBalanceAction;
use App\Domain\Payment\DTOs\SubscriptionBalance;
use App\Domain\Payment\Enums\PaymentStatus;

function payBalance(
    int $totalCents,
    array $paymentAmounts = [],
    array $refundAmounts = [],
    bool $isCancelled = false,
): SubscriptionBalance {
    return (new CalculateSubscriptionBalanceAction)->execute(
        $totalCents,
        $paymentAmounts,
        $refundAmounts,
        $isCancelled,
    );
}

it('is unpaid when nothing has been paid', function () {
    $balance = payBalance(50000);

    expect($balance->status)->toBe(PaymentStatus::Unpaid)
        ->and($balance->paidCents)->toBe(0)
        ->and($balance->refundedCents)->toBe(0)
        ->and($balance->netPaidCents)->toBe(0)
        ->and($balance->outstandingCents)->toBe(50000);
});

it('is partial when net paid is below the total', function () {
    $balance = payBalance(50000, [20000]);

    expect($balance->status)->toBe(PaymentStatus::Partial)
        ->and($balance->netPaidCents)->toBe(20000)
        ->and($balance->outstandingCents)->toBe(30000);
});

it('is paid when net paid reaches the total', function () {
    $balance = payBalance(50000, [20000, 30000]);

    expect($balance->status)->toBe(PaymentStatus::Paid)
        ->and($balance->outstandingCents)->toBe(0);
});

it('is paid for a free plan', function () {
    $balance = payBalance(0);

    expect($balance->status)->toBe(PaymentStatus::Paid)
        ->and($balance->outstandingCents)->toBe(0);
});

it('is refunded when the net paid becomes zero through a refund', function () {
    $balance = payBalance(50000, [50000], [50000]);

    expect($balance->status)->toBe(PaymentStatus::Refunded)
        ->and($balance->netPaidCents)->toBe(0)
        ->and($balance->refundedCents)->toBe(50000)
        ->and($balance->outstandingCents)->toBe(50000);
});

it('reduces net paid with refunds', function () {
    $balance = payBalance(50000, [50000], [20000]);

    expect($balance->status)->toBe(PaymentStatus::Partial)
        ->and($balance->paidCents)->toBe(50000)
        ->and($balance->refundedCents)->toBe(20000)
        ->and($balance->netPaidCents)->toBe(30000)
        ->and($balance->outstandingCents)->toBe(20000);
});

it('treats an overpayment as paid with zero outstanding', function () {
    $balance = payBalance(50000, [60000]);

    expect($balance->status)->toBe(PaymentStatus::Paid)
        ->and($balance->netPaidCents)->toBe(60000)
        ->and($balance->outstandingCents)->toBe(0);
});

it('reopens the outstanding amount when a refund follows an overpayment', function () {
    $balance = payBalance(50000, [60000], [20000]);

    expect($balance->status)->toBe(PaymentStatus::Partial)
        ->and($balance->netPaidCents)->toBe(40000)
        ->and($balance->outstandingCents)->toBe(10000);
});

it('gives zero outstanding when the subscription is cancelled', function () {
    $balance = payBalance(50000, [20000], [], true);

    expect($balance->status)->toBe(PaymentStatus::Partial)
        ->and($balance->outstandingCents)->toBe(0);
});
