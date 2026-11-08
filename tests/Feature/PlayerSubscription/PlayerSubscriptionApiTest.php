<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Membership\Models\MembershipPlan;
use App\Domain\Player\Models\Player;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Domain\Sport\Models\ClubSport;
use App\Domain\Sport\Models\PlayerSport;
use App\Domain\Sport\Models\Sport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

function subApiSportFor(object $tenant): Sport
{
    $sport = Sport::factory()->create();

    app(CurrentClub::class)->runAs($tenant->club, fn () => ClubSport::query()->create(['sport_id' => $sport->id]));

    return $sport;
}

function subApiPlayerFor(object $tenant, Sport $sport): Player
{
    $player = Player::factory()->create(['club_id' => $tenant->club->id]);

    app(CurrentClub::class)->runAs($tenant->club, fn () => PlayerSport::query()->create([
        'player_id' => $player->id,
        'sport_id' => $sport->id,
    ]));

    return $player;
}

function subApiPlanFor(object $tenant, Sport $sport, array $attributes = []): MembershipPlan
{
    return MembershipPlan::factory()->create(array_merge([
        'club_id' => $tenant->club->id,
        'sport_id' => $sport->id,
    ], $attributes));
}

function subApiSubscriptionFor(object $tenant, Player $player, MembershipPlan $plan, array $attributes = []): PlayerSubscription
{
    return PlayerSubscription::factory()->create(array_merge([
        'club_id' => $tenant->club->id,
        'player_id' => $player->id,
        'membership_plan_id' => $plan->id,
        'price_cents' => $plan->price_cents,
        'currency' => $plan->currency,
        'duration_months' => $plan->duration_months,
    ], $attributes));
}

it('creates a subscription and returns the snapshot', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subApiSportFor($tenant);
    $player = subApiPlayerFor($tenant, $sport);
    $plan = subApiPlanFor($tenant, $sport, ['price_cents' => 50000, 'duration_months' => 1]);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/players/{$player->id}/subscriptions", ['membership_plan_id' => $plan->id])
        ->assertCreated()
        ->assertJsonPath('data.player_id', $player->id)
        ->assertJsonPath('data.membership_plan.id', $plan->id)
        ->assertJsonPath('data.membership_plan.sport_id', $sport->id)
        ->assertJsonPath('data.starts_on', '2026-01-10')
        ->assertJsonPath('data.ends_on', '2026-02-10')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.price_cents', 50000)
        ->assertJsonPath('data.currency', 'EGP')
        ->assertJsonPath('data.duration_months', 1)
        ->assertJsonPath('data.discount', null)
        ->assertJsonPath('data.total_cents', 50000)
        ->assertJsonPath('data.renewed_from_id', null);

    expect(Activity::query()->where('event', 'player_subscription.created')->count())->toBe(1);
});

it('creates a subscription with a percent discount', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subApiSportFor($tenant);
    $player = subApiPlayerFor($tenant, $sport);
    $plan = subApiPlanFor($tenant, $sport, ['price_cents' => 50000, 'duration_months' => 1]);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/players/{$player->id}/subscriptions", [
            'membership_plan_id' => $plan->id,
            'starts_on' => '2026-01-15',
            'discount' => ['type' => 'percent', 'value' => 10],
        ])
        ->assertCreated()
        ->assertJsonPath('data.starts_on', '2026-01-15')
        ->assertJsonPath('data.discount.type', 'percent')
        ->assertJsonPath('data.discount.value', 10)
        ->assertJsonPath('data.discount.cents', 5000)
        ->assertJsonPath('data.total_cents', 45000);
});

it('returns validation errors for a bad payload', function () {
    $tenant = TenantFixture::create();
    $sport = subApiSportFor($tenant);
    $player = subApiPlayerFor($tenant, $sport);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/players/{$player->id}/subscriptions", [
            'starts_on' => '15-01-2026',
            'discount' => ['type' => 'half', 'value' => -1],
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation.failed')
        ->assertJsonStructure(['errors' => ['membership_plan_id', 'starts_on', 'discount.type', 'discount.value']]);
});

it('returns the arabic message for a business error', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subApiSportFor($tenant);
    $player = subApiPlayerFor($tenant, $sport);
    $plan = subApiPlanFor($tenant, $sport, ['duration_months' => 1]);

    subApiSubscriptionFor($tenant, $player, $plan, ['starts_on' => '2026-01-01', 'ends_on' => '2026-02-01']);

    $this->withToken($tenant->token)
        ->withHeaders(['Accept-Language' => 'ar'])
        ->postJson("/api/v1/players/{$player->id}/subscriptions", ['membership_plan_id' => $plan->id])
        ->assertStatus(422)
        ->assertJsonPath('code', 'player_subscription.overlap')
        ->assertJsonPath('message', trans('errors.player_subscription.overlap', [], 'ar'));
});

it('returns the arabic message for a validation failure', function () {
    $tenant = TenantFixture::create();
    $sport = subApiSportFor($tenant);
    $player = subApiPlayerFor($tenant, $sport);

    $this->withToken($tenant->token)
        ->withHeaders(['Accept-Language' => 'ar'])
        ->postJson("/api/v1/players/{$player->id}/subscriptions", [])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation.failed')
        ->assertJsonPath('message', trans('errors.validation.failed', [], 'ar'));
});

it('lets a coach read but not write subscriptions', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create(RoleName::Coach);
    $sport = subApiSportFor($tenant);
    $player = subApiPlayerFor($tenant, $sport);
    $plan = subApiPlanFor($tenant, $sport, ['duration_months' => 1]);
    $subscription = subApiSubscriptionFor($tenant, $player, $plan, [
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-02-01',
    ]);

    $this->withToken($tenant->token)
        ->getJson("/api/v1/players/{$player->id}/subscriptions")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson("/api/v1/player-subscriptions/{$subscription->id}")
        ->assertOk();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson("/api/v1/players/{$player->id}/subscriptions", ['membership_plan_id' => $plan->id])
        ->assertForbidden()
        ->assertJsonPath('code', 'http.forbidden');

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson("/api/v1/player-subscriptions/{$subscription->id}/renew", [])
        ->assertForbidden();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson("/api/v1/player-subscriptions/{$subscription->id}/cancel", [])
        ->assertForbidden();
});

it('forbids a referee from reading subscriptions', function () {
    $tenant = TenantFixture::create(RoleName::Referee);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/player-subscriptions')
        ->assertForbidden();
});

it('returns 404 for another club player or subscription', function () {
    $tenant = TenantFixture::create();
    $sport = subApiSportFor($tenant);
    $plan = subApiPlanFor($tenant, $sport);

    $other = TenantFixture::create();
    $otherSport = subApiSportFor($other);
    $otherPlayer = subApiPlayerFor($other, $otherSport);
    $otherPlan = subApiPlanFor($other, $otherSport);
    $otherSubscription = subApiSubscriptionFor($other, $otherPlayer, $otherPlan);

    $this->withToken($tenant->token)
        ->getJson("/api/v1/players/{$otherPlayer->id}/subscriptions")
        ->assertNotFound();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson("/api/v1/players/{$otherPlayer->id}/subscriptions", ['membership_plan_id' => $plan->id])
        ->assertNotFound();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson("/api/v1/player-subscriptions/{$otherSubscription->id}")
        ->assertNotFound();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson("/api/v1/player-subscriptions/{$otherSubscription->id}/renew", [])
        ->assertNotFound();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson("/api/v1/player-subscriptions/{$otherSubscription->id}/cancel", [])
        ->assertNotFound();
});

it('rejects a plan of another club through validation', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subApiSportFor($tenant);
    $player = subApiPlayerFor($tenant, $sport);

    $other = TenantFixture::create();
    $otherSport = subApiSportFor($other);
    $otherPlan = subApiPlanFor($other, $otherSport);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/players/{$player->id}/subscriptions", ['membership_plan_id' => $otherPlan->id])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation.failed')
        ->assertJsonStructure(['errors' => ['membership_plan_id']]);
});

it('lists, sorts and filters subscriptions', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-15 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subApiSportFor($tenant);
    $player = subApiPlayerFor($tenant, $sport);
    $otherPlayer = subApiPlayerFor($tenant, $sport);
    $plan = subApiPlanFor($tenant, $sport);
    $otherPlan = subApiPlanFor($tenant, $sport);

    $first = subApiSubscriptionFor($tenant, $player, $plan, [
        'starts_on' => '2026-01-01', 'ends_on' => '2026-02-01',
    ]);
    $second = subApiSubscriptionFor($tenant, $player, $otherPlan, [
        'starts_on' => '2026-01-05', 'ends_on' => '2026-04-05',
    ]);
    $expired = subApiSubscriptionFor($tenant, $otherPlayer, $plan, [
        'starts_on' => '2025-11-01', 'ends_on' => '2025-12-01',
    ]);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/player-subscriptions')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.*.id', [$second->id, $first->id, $expired->id]);

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson("/api/v1/player-subscriptions?player_id={$player->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson("/api/v1/player-subscriptions?membership_plan_id={$plan->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.*.id', [$first->id, $expired->id]);
});

it('filters by the three computed statuses with frozen time', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subApiSportFor($tenant);
    $player = subApiPlayerFor($tenant, $sport);
    $plan = subApiPlanFor($tenant, $sport);

    $active = subApiSubscriptionFor($tenant, $player, $plan, [
        'starts_on' => '2026-05-01', 'ends_on' => '2026-07-01',
    ]);
    $expired = subApiSubscriptionFor($tenant, $player, $plan, [
        'starts_on' => '2026-03-01', 'ends_on' => '2026-05-01',
    ]);
    $cancelled = subApiSubscriptionFor($tenant, $player, $plan, [
        'starts_on' => '2026-05-01', 'ends_on' => '2026-07-01', 'cancelled_at' => now(),
    ]);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/player-subscriptions?status=active')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $active->id)
        ->assertJsonPath('data.0.status', 'active');

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson('/api/v1/player-subscriptions?status=expired')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$expired->id])
        ->assertJsonPath('data.0.status', 'expired');

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson('/api/v1/player-subscriptions?status=cancelled')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$cancelled->id])
        ->assertJsonPath('data.0.status', 'cancelled');
});

it('paginates and caps the page size at 100', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-15 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subApiSportFor($tenant);
    $player = subApiPlayerFor($tenant, $sport);
    $plan = subApiPlanFor($tenant, $sport);

    foreach (['2026-01-01', '2026-01-02', '2026-01-03'] as $start) {
        subApiSubscriptionFor($tenant, $player, $plan, [
            'starts_on' => $start,
            'ends_on' => '2026-04-01',
        ]);
    }

    $this->withToken($tenant->token)
        ->getJson('/api/v1/player-subscriptions?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 2);

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson('/api/v1/player-subscriptions?per_page=500')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

it('renews a subscription through the API', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subApiSportFor($tenant);
    $player = subApiPlayerFor($tenant, $sport);
    $plan = subApiPlanFor($tenant, $sport, ['duration_months' => 1]);
    $previous = subApiSubscriptionFor($tenant, $player, $plan, [
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-02-01',
    ]);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/player-subscriptions/{$previous->id}/renew", [])
        ->assertCreated()
        ->assertJsonPath('data.starts_on', '2026-02-01')
        ->assertJsonPath('data.ends_on', '2026-03-01')
        ->assertJsonPath('data.renewed_from_id', $previous->id);

    expect(Activity::query()->where('event', 'player_subscription.renewed')->count())->toBe(1);
});

it('cancels a subscription through the API', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subApiSportFor($tenant);
    $player = subApiPlayerFor($tenant, $sport);
    $plan = subApiPlanFor($tenant, $sport);
    $subscription = subApiSubscriptionFor($tenant, $player, $plan, [
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-02-01',
    ]);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/player-subscriptions/{$subscription->id}/cancel", [])
        ->assertOk()
        ->assertJsonPath('data.id', $subscription->id)
        ->assertJsonPath('data.status', 'cancelled');

    expect(Activity::query()->where('event', 'player_subscription.cancelled')->count())->toBe(1);
});

it('rejects renewing a cancelled subscription through the API', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subApiSportFor($tenant);
    $player = subApiPlayerFor($tenant, $sport);
    $plan = subApiPlanFor($tenant, $sport);
    $subscription = subApiSubscriptionFor($tenant, $player, $plan, [
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-02-01',
        'cancelled_at' => now(),
    ]);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/player-subscriptions/{$subscription->id}/renew", [])
        ->assertStatus(422)
        ->assertJsonPath('code', 'player_subscription.cannot_renew_cancelled');
});

it('rejects cancelling an expired subscription through the API', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-01 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subApiSportFor($tenant);
    $player = subApiPlayerFor($tenant, $sport);
    $plan = subApiPlanFor($tenant, $sport);
    $subscription = subApiSubscriptionFor($tenant, $player, $plan, [
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-02-01',
    ]);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/player-subscriptions/{$subscription->id}/cancel", [])
        ->assertStatus(422)
        ->assertJsonPath('code', 'player_subscription.not_cancellable');
});
