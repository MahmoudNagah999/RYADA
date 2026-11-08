<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Membership\Models\MembershipPlan;
use App\Domain\Payment\Actions\GetSubscriptionBalanceAction;
use App\Domain\Payment\Actions\RecordPaymentAction;
use App\Domain\Payment\Actions\RefundPaymentAction;
use App\Domain\Payment\Actions\VoidPaymentAction;
use App\Domain\Payment\DTOs\RecordPaymentData;
use App\Domain\Payment\DTOs\RefundPaymentData;
use App\Domain\Payment\DTOs\SubscriptionBalance;
use App\Domain\Payment\DTOs\VoidPaymentData;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\PaymentRefund;
use App\Domain\Player\Models\Player;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Shared\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

function payInClub(TenantFixture $tenant, Closure $callback): mixed
{
    return app(CurrentClub::class)->runAs($tenant->club, $callback);
}

function paySetup(TenantFixture $tenant, array $attributes = []): PlayerSubscription
{
    $plan = MembershipPlan::factory()->create(['club_id' => $tenant->club->id]);
    $player = Player::factory()->create(['club_id' => $tenant->club->id]);

    return PlayerSubscription::factory()->create(array_merge([
        'club_id' => $tenant->club->id,
        'player_id' => $player->id,
        'membership_plan_id' => $plan->id,
        'total_cents' => 50000,
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-02-01',
    ], $attributes));
}

function payRecord(TenantFixture $tenant, PlayerSubscription $subscription, int $amount = 50000, array $overrides = []): Payment
{
    return payInClub($tenant, fn () => app(RecordPaymentAction::class)->execute(new RecordPaymentData(
        subscriptionId: $subscription->id,
        amountCents: $amount,
        method: $overrides['method'] ?? PaymentMethod::Cash,
        paidOn: $overrides['paidOn'] ?? null,
        reference: $overrides['reference'] ?? null,
        note: $overrides['note'] ?? null,
        recordedBy: $overrides['recordedBy'] ?? $tenant->user->id,
    )));
}

function payVoid(TenantFixture $tenant, Payment $payment, string $reason = 'Recorded by mistake'): Payment
{
    return payInClub($tenant, fn () => app(VoidPaymentAction::class)->execute(
        new VoidPaymentData($payment->id, $reason, $tenant->user->id),
    ));
}

function payRefund(TenantFixture $tenant, Payment $payment, int $amount, array $overrides = []): PaymentRefund
{
    return payInClub($tenant, fn () => app(RefundPaymentAction::class)->execute(new RefundPaymentData(
        paymentId: $payment->id,
        amountCents: $amount,
        method: $overrides['method'] ?? PaymentMethod::Cash,
        reason: $overrides['reason'] ?? 'Customer request',
        refundedOn: $overrides['refundedOn'] ?? null,
        recordedBy: $tenant->user->id,
    )));
}

function payBalanceOf(TenantFixture $tenant, PlayerSubscription $subscription): SubscriptionBalance
{
    return payInClub($tenant, fn () => app(GetSubscriptionBalanceAction::class)->execute($subscription));
}

function payCatch(Closure $callback): ?BusinessRuleException
{
    try {
        $callback();
    } catch (BusinessRuleException $e) {
        return $e;
    }

    return null;
}

function payAuditCount(string $event): int
{
    return Activity::query()->where('event', $event)->count();
}

it('allocates receipt numbers per club and independently', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $firstClub = TenantFixture::create();
    $secondClub = TenantFixture::create();

    $firstSubscription = paySetup($firstClub);
    $secondSubscription = paySetup($secondClub);

    $first = payRecord($firstClub, $firstSubscription, 10000);
    $second = payRecord($firstClub, $firstSubscription, 10000);
    $other = payRecord($secondClub, $secondSubscription, 10000);

    expect($first->receipt_number)->toBe(1)
        ->and($second->receipt_number)->toBe(2)
        ->and($other->receipt_number)->toBe(1)
        ->and(payAuditCount('payment.recorded'))->toBe(3);
});

it('rejects an amount above the outstanding balance', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $subscription = paySetup($tenant);

    $error = payCatch(fn () => payRecord($tenant, $subscription, 50001));

    expect($error?->errorCode)->toBe('payment.exceeds_outstanding')
        ->and($error?->params)->toBe(['outstanding' => 50000])
        ->and(Payment::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(payAuditCount('payment.recorded'))->toBe(0);
});

it('rejects a cancelled subscription', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $subscription = paySetup($tenant, ['cancelled_at' => now()]);

    $error = payCatch(fn () => payRecord($tenant, $subscription, 10000));

    expect($error?->errorCode)->toBe('payment.subscription_cancelled')
        ->and(Payment::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(payAuditCount('payment.recorded'))->toBe(0);
});

it('accepts a payment on an expired subscription', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-01 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $subscription = paySetup($tenant, [
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-02-01',
    ]);

    $payment = payRecord($tenant, $subscription, 50000);

    expect($payment->exists)->toBeTrue()
        ->and(payBalanceOf($tenant, $subscription)->status)->toBe(PaymentStatus::Paid);
});

it('rejects a payment date in the future', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $subscription = paySetup($tenant);

    $error = payCatch(fn () => payRecord($tenant, $subscription, 10000, ['paidOn' => '2026-01-11']));

    expect($error?->errorCode)->toBe('payment.date_in_future')
        ->and(Payment::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('reaches paid after partial payments', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $subscription = paySetup($tenant);

    $first = payRecord($tenant, $subscription, 20000);
    $partial = payBalanceOf($tenant, $subscription);

    $second = payRecord($tenant, $subscription, 30000);
    $paid = payBalanceOf($tenant, $subscription);

    expect($partial->status)->toBe(PaymentStatus::Partial)
        ->and($partial->outstandingCents)->toBe(30000)
        ->and($paid->status)->toBe(PaymentStatus::Paid)
        ->and($paid->outstandingCents)->toBe(0)
        ->and([$first->receipt_number, $second->receipt_number])->toBe([1, 2]);
});

it('voids a payment with a reason and removes it from the balance', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $subscription = paySetup($tenant);
    $payment = payRecord($tenant, $subscription, 50000);

    $voided = payVoid($tenant, $payment, 'Duplicate entry');
    $balance = payBalanceOf($tenant, $subscription);

    expect($voided->voided_at)->not->toBeNull()
        ->and($voided->void_reason)->toBe('Duplicate entry')
        ->and($voided->voided_by)->toBe($tenant->user->id)
        ->and($balance->status)->toBe(PaymentStatus::Unpaid)
        ->and($balance->outstandingCents)->toBe(50000)
        ->and(payAuditCount('payment.voided'))->toBe(1);
});

it('rejects voiding an already voided payment', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $subscription = paySetup($tenant);
    $payment = payRecord($tenant, $subscription, 10000);

    payVoid($tenant, $payment);

    $error = payCatch(fn () => payVoid($tenant, $payment));

    expect($error?->errorCode)->toBe('payment.already_voided')
        ->and(payAuditCount('payment.voided'))->toBe(1);
});

it('blocks voiding a payment that has refunds', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $subscription = paySetup($tenant);
    $payment = payRecord($tenant, $subscription, 50000);

    payRefund($tenant, $payment, 10000);

    $error = payCatch(fn () => payVoid($tenant, $payment));

    expect($error?->errorCode)->toBe('payment.void_has_refunds')
        ->and(payAuditCount('payment.voided'))->toBe(0);
});

it('reduces the net paid with a refund', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $subscription = paySetup($tenant);
    $payment = payRecord($tenant, $subscription, 50000);

    payRefund($tenant, $payment, 20000);
    $balance = payBalanceOf($tenant, $subscription);

    expect($balance->paidCents)->toBe(50000)
        ->and($balance->refundedCents)->toBe(20000)
        ->and($balance->netPaidCents)->toBe(30000)
        ->and($balance->outstandingCents)->toBe(20000)
        ->and($balance->status)->toBe(PaymentStatus::Partial)
        ->and(payAuditCount('payment.refunded'))->toBe(1);
});

it('allocates refund numbers per club', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $firstClub = TenantFixture::create();
    $secondClub = TenantFixture::create();

    $firstSubscription = paySetup($firstClub);
    $secondSubscription = paySetup($secondClub);

    $firstPayment = payRecord($firstClub, $firstSubscription, 50000);
    $secondPayment = payRecord($secondClub, $secondSubscription, 50000);

    $first = payRefund($firstClub, $firstPayment, 10000);
    $second = payRefund($firstClub, $firstPayment, 10000);
    $other = payRefund($secondClub, $secondPayment, 10000);

    expect($first->refund_number)->toBe(1)
        ->and($second->refund_number)->toBe(2)
        ->and($other->refund_number)->toBe(1);
});

it('rejects a refund above the refundable amount', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $subscription = paySetup($tenant);
    $payment = payRecord($tenant, $subscription, 50000);

    $error = payCatch(fn () => payRefund($tenant, $payment, 50001));

    expect($error?->errorCode)->toBe('payment.refund_exceeds_payment')
        ->and($error?->params)->toBe(['refundable' => 50000])
        ->and(PaymentRefund::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(payAuditCount('payment.refunded'))->toBe(0);
});

it('rejects a refund on a voided payment', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $subscription = paySetup($tenant);
    $payment = payRecord($tenant, $subscription, 50000);

    payVoid($tenant, $payment);

    $error = payCatch(fn () => payRefund($tenant, $payment, 10000));

    expect($error?->errorCode)->toBe('payment.voided')
        ->and(PaymentRefund::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('allows a refund on a cancelled subscription', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $subscription = paySetup($tenant);

    $payment = Payment::factory()->create([
        'club_id' => $tenant->club->id,
        'player_subscription_id' => $subscription->id,
        'amount_cents' => 50000,
    ]);

    $subscription->update(['cancelled_at' => now()]);

    $refund = payRefund($tenant, $payment, 10000);

    expect($refund->exists)->toBeTrue()
        ->and($refund->refund_number)->toBe(1);
});

it('respects the remaining refundable amount on a second partial refund', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $subscription = paySetup($tenant);
    $payment = payRecord($tenant, $subscription, 50000);

    payRefund($tenant, $payment, 30000);

    $error = payCatch(fn () => payRefund($tenant, $payment, 30000));

    $second = payRefund($tenant, $payment, 20000);

    expect($error?->errorCode)->toBe('payment.refund_exceeds_payment')
        ->and($error?->params)->toBe(['refundable' => 20000])
        ->and($second->amount_cents)->toBe(20000)
        ->and($second->refund_number)->toBe(2);
});
