<?php

use App\Domain\Membership\Models\MembershipPlan;
use App\Domain\Payment\Actions\ListDueSubscriptionsAction;
use App\Domain\Payment\DTOs\ListDueSubscriptionsData;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\PaymentRefund;
use App\Domain\Player\Models\Player;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use App\Domain\Shared\Tenancy\CurrentClub;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

function payDueInClub(TenantFixture $tenant, Closure $callback): mixed
{
    return app(CurrentClub::class)->runAs($tenant->club, $callback);
}

function payDueSubscription(TenantFixture $tenant, array $attributes = []): PlayerSubscription
{
    $plan = MembershipPlan::factory()->create(['club_id' => $tenant->club->id]);
    $player = Player::factory()->create(['club_id' => $tenant->club->id]);

    return PlayerSubscription::factory()->create(array_merge([
        'club_id' => $tenant->club->id,
        'player_id' => $player->id,
        'membership_plan_id' => $plan->id,
        'total_cents' => 50000,
        'starts_on' => '2026-05-01',
        'ends_on' => '2026-06-05',
    ], $attributes));
}

function payDuePayment(TenantFixture $tenant, PlayerSubscription $subscription, int $amount, array $attributes = []): Payment
{
    return payDueInClub($tenant, fn () => Payment::factory()->create(array_merge([
        'club_id' => $tenant->club->id,
        'player_subscription_id' => $subscription->id,
        'amount_cents' => $amount,
        'paid_on' => '2026-05-01',
    ], $attributes)));
}

function payDueRefund(TenantFixture $tenant, Payment $payment, int $amount): PaymentRefund
{
    return payDueInClub($tenant, fn () => PaymentRefund::factory()->create([
        'club_id' => $tenant->club->id,
        'payment_id' => $payment->id,
        'amount_cents' => $amount,
        'refunded_on' => '2026-05-01',
    ]));
}

function payDueList(TenantFixture $tenant, string $type = 'all', int $perPage = 100): LengthAwarePaginator
{
    return payDueInClub($tenant, fn () => app(ListDueSubscriptionsAction::class)->execute(
        new ListDueSubscriptionsData($type, 7, $perPage),
    ));
}

it('does not run per-row queries when listing due subscriptions', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();

    foreach (range(1, 3) as $ignored) {
        payDueSubscription($tenant);
    }

    DB::enableQueryLog();
    payDueList($tenant);
    $baseline = count(DB::getQueryLog());

    foreach (range(1, 27) as $ignored) {
        $subscription = payDueSubscription($tenant);
        $payment = payDuePayment($tenant, $subscription, 20000);
        payDueRefund($tenant, $payment, 5000);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    payDueList($tenant);
    $after = count(DB::getQueryLog());

    expect($after)->toBe($baseline);
});

it('excludes voided payments and the refunds recorded against them', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $subscription = payDueSubscription($tenant);

    $active = payDuePayment($tenant, $subscription, 20000);
    payDueRefund($tenant, $active, 5000);

    $voided = payDuePayment($tenant, $subscription, 30000, ['voided_at' => now()]);
    payDueRefund($tenant, $voided, 10000);

    $rows = payDueList($tenant)->items();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->balance->paidCents)->toBe(20000)
        ->and($rows[0]->balance->refundedCents)->toBe(5000)
        ->and($rows[0]->balance->netPaidCents)->toBe(15000)
        ->and($rows[0]->balance->outstandingCents)->toBe(35000);
});
