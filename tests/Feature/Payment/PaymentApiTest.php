<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Membership\Models\MembershipPlan;
use App\Domain\Payment\Models\Payment;
use App\Domain\Player\Models\Player;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use App\Domain\Shared\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

function payApiInClub(TenantFixture $tenant, Closure $callback): mixed
{
    return app(CurrentClub::class)->runAs($tenant->club, $callback);
}

function payApiSetup(TenantFixture $tenant, array $attributes = []): PlayerSubscription
{
    $plan = MembershipPlan::factory()->create([
        'club_id' => $tenant->club->id,
        'price_cents' => 50000,
    ]);
    $player = Player::factory()->create([
        'club_id' => $tenant->club->id,
        'name' => 'Ali Hassan',
    ]);

    return PlayerSubscription::factory()->create(array_merge([
        'club_id' => $tenant->club->id,
        'player_id' => $player->id,
        'membership_plan_id' => $plan->id,
        'total_cents' => 50000,
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-02-01',
    ], $attributes));
}

function payApiPayment(TenantFixture $tenant, PlayerSubscription $subscription, array $attributes = []): Payment
{
    return payApiInClub($tenant, fn () => Payment::factory()->create(array_merge([
        'club_id' => $tenant->club->id,
        'player_subscription_id' => $subscription->id,
        'amount_cents' => 50000,
        'paid_on' => '2026-01-10',
    ], $attributes)));
}

it('records a payment and exposes it through the list and balance endpoints', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $subscription = payApiSetup($tenant);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/player-subscriptions/{$subscription->id}/payments", [
            'amount_cents' => 20000,
            'method' => 'cash',
            'reference' => 'INV-1',
            'note' => 'First installment',
        ])
        ->assertCreated()
        ->assertJsonPath('data.receipt_number', 1)
        ->assertJsonPath('data.receipt_code', 'RCP-000001')
        ->assertJsonPath('data.player_subscription_id', $subscription->id)
        ->assertJsonPath('data.amount_cents', 20000)
        ->assertJsonPath('data.method', 'cash')
        ->assertJsonPath('data.paid_on', '2026-01-10')
        ->assertJsonPath('data.reference', 'INV-1')
        ->assertJsonPath('data.note', 'First installment')
        ->assertJsonPath('data.voided', false)
        ->assertJsonPath('data.refunded_cents', 0);

    expect(Activity::query()->where('event', 'payment.recorded')->count())->toBe(1);

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson("/api/v1/player-subscriptions/{$subscription->id}/payments")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.receipt_code', 'RCP-000001');

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson("/api/v1/player-subscriptions/{$subscription->id}/balance")
        ->assertOk()
        ->assertJsonPath('data.total_cents', 50000)
        ->assertJsonPath('data.paid_cents', 20000)
        ->assertJsonPath('data.refunded_cents', 0)
        ->assertJsonPath('data.net_paid_cents', 20000)
        ->assertJsonPath('data.outstanding_cents', 30000)
        ->assertJsonPath('data.status', 'partial');
});

it('returns validation errors for a bad payment payload', function () {
    $tenant = TenantFixture::create();
    $subscription = payApiSetup($tenant);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/player-subscriptions/{$subscription->id}/payments", [])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation.failed')
        ->assertJsonStructure(['errors' => ['amount_cents', 'method']]);

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson("/api/v1/player-subscriptions/{$subscription->id}/payments", [
            'amount_cents' => 1000,
            'method' => 'cash',
            'paid_on' => '10-01-2026',
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['paid_on']]);
});

it('returns the arabic message for a cancelled subscription', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $subscription = payApiSetup($tenant, ['cancelled_at' => now()]);

    $this->withToken($tenant->token)
        ->withHeaders(['Accept-Language' => 'ar'])
        ->postJson("/api/v1/player-subscriptions/{$subscription->id}/payments", [
            'amount_cents' => 10000,
            'method' => 'cash',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'payment.subscription_cancelled')
        ->assertJsonPath('message', trans('errors.payment.subscription_cancelled', [], 'ar'));
});

it('forbids staff from refunding', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create(RoleName::Staff);
    $subscription = payApiSetup($tenant);
    $payment = payApiPayment($tenant, $subscription);

    $this->withToken($tenant->token)
        ->getJson("/api/v1/player-subscriptions/{$subscription->id}/payments")
        ->assertOk();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson("/api/v1/payments/{$payment->id}/refunds", [
            'amount_cents' => 10000,
            'method' => 'cash',
            'reason' => 'Requested by the member',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'http.forbidden');
});

it('forbids coaches and referees from reading payments', function () {
    $coach = TenantFixture::create(RoleName::Coach);
    $referee = TenantFixture::create(RoleName::Referee);
    $coachSubscription = payApiSetup($coach);

    $this->withToken($coach->token)
        ->getJson("/api/v1/player-subscriptions/{$coachSubscription->id}/payments")
        ->assertForbidden();

    $this->app['auth']->forgetGuards();

    $this->withToken($coach->token)
        ->getJson('/api/v1/player-subscriptions/due')
        ->assertForbidden();

    $this->app['auth']->forgetGuards();

    $this->withToken($referee->token)
        ->getJson('/api/v1/player-subscriptions/due')
        ->assertForbidden();
});

it('returns 404 for another club subscription and payment', function () {
    $tenant = TenantFixture::create();
    $other = TenantFixture::create();

    $otherSubscription = payApiSetup($other);
    $otherPayment = payApiPayment($other, $otherSubscription);

    $this->withToken($tenant->token)
        ->getJson("/api/v1/player-subscriptions/{$otherSubscription->id}/payments")
        ->assertNotFound();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson("/api/v1/player-subscriptions/{$otherSubscription->id}/payments", [
            'amount_cents' => 1000,
            'method' => 'cash',
        ])
        ->assertNotFound();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson("/api/v1/player-subscriptions/{$otherSubscription->id}/balance")
        ->assertNotFound();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson("/api/v1/payments/{$otherPayment->id}/receipt")
        ->assertNotFound();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson("/api/v1/payments/{$otherPayment->id}/void", ['reason' => 'Nope'])
        ->assertNotFound();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson("/api/v1/payments/{$otherPayment->id}/refunds", [
            'amount_cents' => 1000,
            'method' => 'cash',
            'reason' => 'Nope',
        ])
        ->assertNotFound();
});

it('returns the receipt content', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $tenant->club->update(['contact_email' => 'club@example.com', 'city' => 'Cairo']);

    $plan = MembershipPlan::factory()->create([
        'club_id' => $tenant->club->id,
        'name' => 'Gold Annual',
    ]);
    $player = Player::factory()->create([
        'club_id' => $tenant->club->id,
        'name' => 'Ali Hassan',
    ]);
    $subscription = PlayerSubscription::factory()->create([
        'club_id' => $tenant->club->id,
        'player_id' => $player->id,
        'membership_plan_id' => $plan->id,
        'total_cents' => 50000,
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-02-01',
    ]);
    $payment = payApiPayment($tenant, $subscription, [
        'receipt_number' => 1,
        'amount_cents' => 50000,
        'reference' => 'INV-9',
    ]);

    $this->withToken($tenant->token)
        ->getJson("/api/v1/payments/{$payment->id}/receipt")
        ->assertOk()
        ->assertJsonPath('data.receipt_code', 'RCP-000001')
        ->assertJsonPath('data.paid_on', '2026-01-10')
        ->assertJsonPath('data.amount_cents', 50000)
        ->assertJsonPath('data.method', 'cash')
        ->assertJsonPath('data.reference', 'INV-9')
        ->assertJsonPath('data.voided', false)
        ->assertJsonPath('data.club.name', $tenant->club->name)
        ->assertJsonPath('data.club.contact_email', 'club@example.com')
        ->assertJsonPath('data.club.city', 'Cairo')
        ->assertJsonPath('data.player.name', 'Ali Hassan')
        ->assertJsonPath('data.subscription.id', $subscription->id)
        ->assertJsonPath('data.subscription.plan', 'Gold Annual')
        ->assertJsonPath('data.subscription.total_cents', 50000)
        ->assertJsonPath('data.balance.status', 'paid')
        ->assertJsonPath('data.balance.outstanding_cents', 0);
});

it('voids a payment through the API', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $subscription = payApiSetup($tenant);
    $payment = payApiPayment($tenant, $subscription, ['amount_cents' => 10000]);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/payments/{$payment->id}/void", ['reason' => 'Duplicate entry'])
        ->assertOk()
        ->assertJsonPath('data.voided', true)
        ->assertJsonPath('data.void_reason', 'Duplicate entry')
        ->assertJsonPath('data.refunds', []);

    expect(Activity::query()->where('event', 'payment.voided')->count())->toBe(1);
});

it('refunds a payment through the API', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $subscription = payApiSetup($tenant);
    $payment = payApiPayment($tenant, $subscription, ['amount_cents' => 50000]);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/payments/{$payment->id}/refunds", [
            'amount_cents' => 20000,
            'method' => 'bank_transfer',
            'reason' => 'Partial refund',
        ])
        ->assertCreated()
        ->assertJsonPath('data.refund_number', 1)
        ->assertJsonPath('data.refund_code', 'REF-000001')
        ->assertJsonPath('data.amount_cents', 20000)
        ->assertJsonPath('data.method', 'bank_transfer')
        ->assertJsonPath('data.refunded_on', '2026-01-10')
        ->assertJsonPath('data.reason', 'Partial refund');

    expect(Activity::query()->where('event', 'payment.refunded')->count())->toBe(1);
});

it('lists due subscriptions with expiring, unpaid and all filters', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();

    $expiring = payApiSetup($tenant, [
        'starts_on' => '2026-05-01',
        'ends_on' => '2026-06-05',
        'total_cents' => 50000,
    ]);
    $unpaid = payApiSetup($tenant, [
        'starts_on' => '2026-05-01',
        'ends_on' => '2026-12-01',
        'total_cents' => 50000,
    ]);
    payApiSetup($tenant, [
        'starts_on' => '2026-05-01',
        'ends_on' => '2026-06-05',
        'cancelled_at' => now(),
    ]);

    payApiPayment($tenant, $expiring, ['amount_cents' => 50000]);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/player-subscriptions/due?type=expiring')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $expiring->id)
        ->assertJsonPath('data.0.payment_status', 'paid')
        ->assertJsonPath('data.0.paid_cents', 50000)
        ->assertJsonPath('data.0.outstanding_cents', 0);

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson('/api/v1/player-subscriptions/due?type=unpaid')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $unpaid->id)
        ->assertJsonPath('data.0.payment_status', 'unpaid')
        ->assertJsonPath('data.0.outstanding_cents', 50000);

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson('/api/v1/player-subscriptions/due')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.*.id', [$expiring->id, $unpaid->id]);

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson('/api/v1/player-subscriptions/due?per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.per_page', 1);

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson('/api/v1/player-subscriptions/due?per_page=500')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

it('validates the due list filter parameters', function () {
    $tenant = TenantFixture::create();

    $this->withToken($tenant->token)
        ->getJson('/api/v1/player-subscriptions/due?type=soon&expiring_within_days=90&per_page=0')
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['type', 'expiring_within_days', 'per_page']]);
});
