<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Membership\DTOs\MembershipDiscount;
use App\Domain\Membership\Models\MembershipPlan;
use App\Domain\Player\Actions\DeletePlayerAction;
use App\Domain\Player\Models\Player;
use App\Domain\PlayerSubscription\Actions\CancelPlayerSubscriptionAction;
use App\Domain\PlayerSubscription\Actions\RenewPlayerSubscriptionAction;
use App\Domain\PlayerSubscription\Actions\SubscribePlayerAction;
use App\Domain\PlayerSubscription\DTOs\CancelPlayerSubscriptionData;
use App\Domain\PlayerSubscription\DTOs\RenewPlayerSubscriptionData;
use App\Domain\PlayerSubscription\DTOs\SubscribePlayerData;
use App\Domain\PlayerSubscription\Enums\SubscriptionStatus;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Domain\Sport\Models\ClubSport;
use App\Domain\Sport\Models\PlayerSport;
use App\Domain\Sport\Models\Sport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

function subActionSportFor(object $tenant): Sport
{
    $sport = Sport::factory()->create();

    app(CurrentClub::class)->runAs($tenant->club, fn () => ClubSport::query()->create(['sport_id' => $sport->id]));

    return $sport;
}

function subActionPlayerFor(object $tenant, Sport $sport): Player
{
    $player = Player::factory()->create(['club_id' => $tenant->club->id]);

    app(CurrentClub::class)->runAs($tenant->club, fn () => PlayerSport::query()->create([
        'player_id' => $player->id,
        'sport_id' => $sport->id,
    ]));

    return $player;
}

function subActionPlanFor(object $tenant, Sport $sport, array $attributes = []): MembershipPlan
{
    return MembershipPlan::factory()->create(array_merge([
        'club_id' => $tenant->club->id,
        'sport_id' => $sport->id,
    ], $attributes));
}

function subActionInClub(object $tenant, Closure $callback): mixed
{
    return app(CurrentClub::class)->runAs($tenant->club, $callback);
}

function subActionSubscribe(
    object $tenant,
    Player $player,
    MembershipPlan $plan,
    ?string $startsOn = null,
    ?MembershipDiscount $discount = null,
): PlayerSubscription {
    return subActionInClub($tenant, fn () => app(SubscribePlayerAction::class)->execute(
        new SubscribePlayerData($player->id, $plan->id, $startsOn, $discount),
    ));
}

function subActionCatch(Closure $callback): ?BusinessRuleException
{
    try {
        $callback();
    } catch (BusinessRuleException $e) {
        return $e;
    }

    return null;
}

it('snapshots the plan on subscription', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subActionSportFor($tenant);
    $player = subActionPlayerFor($tenant, $sport);
    $plan = subActionPlanFor($tenant, $sport, ['price_cents' => 50000, 'duration_months' => 1]);

    $subscription = subActionSubscribe($tenant, $player, $plan);

    expect($subscription->club_id)->toBe($tenant->club->id)
        ->and($subscription->starts_on->toDateString())->toBe('2026-01-10')
        ->and($subscription->ends_on->toDateString())->toBe('2026-02-10')
        ->and($subscription->price_cents)->toBe(50000)
        ->and($subscription->currency)->toBe('EGP')
        ->and($subscription->duration_months)->toBe(1)
        ->and($subscription->discount_cents)->toBe(0)
        ->and($subscription->total_cents)->toBe(50000);

    expect(Activity::query()->where('event', 'player_subscription.created')->count())->toBe(1);
});

it('applies a percent discount', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subActionSportFor($tenant);
    $player = subActionPlayerFor($tenant, $sport);
    $plan = subActionPlanFor($tenant, $sport, ['price_cents' => 50000, 'duration_months' => 1]);

    $subscription = subActionSubscribe($tenant, $player, $plan, null, MembershipDiscount::percent(10));

    expect($subscription->discount_cents)->toBe(5000)
        ->and($subscription->discount_value)->toBe(10)
        ->and($subscription->total_cents)->toBe(45000)
        ->and($subscription->discount_type->value)->toBe('percent');
});

it('applies a fixed discount', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subActionSportFor($tenant);
    $player = subActionPlayerFor($tenant, $sport);
    $plan = subActionPlanFor($tenant, $sport, ['price_cents' => 50000, 'duration_months' => 1]);

    $subscription = subActionSubscribe($tenant, $player, $plan, null, MembershipDiscount::fixed(12500));

    expect($subscription->discount_cents)->toBe(12500)
        ->and($subscription->discount_value)->toBe(12500)
        ->and($subscription->total_cents)->toBe(37500)
        ->and($subscription->discount_type->value)->toBe('fixed');
});

it('accepts a free plan', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subActionSportFor($tenant);
    $player = subActionPlayerFor($tenant, $sport);
    $plan = subActionPlanFor($tenant, $sport, ['price_cents' => 0, 'duration_months' => 2]);

    $subscription = subActionSubscribe($tenant, $player, $plan);

    expect($subscription->price_cents)->toBe(0)
        ->and($subscription->discount_cents)->toBe(0)
        ->and($subscription->total_cents)->toBe(0)
        ->and($subscription->ends_on->toDateString())->toBe('2026-03-10');
});

it('uses addMonthsNoOverflow for an end-of-month start', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subActionSportFor($tenant);
    $player = subActionPlayerFor($tenant, $sport);
    $plan = subActionPlanFor($tenant, $sport, ['duration_months' => 1]);

    $subscription = subActionSubscribe($tenant, $player, $plan, '2026-01-31');

    expect($subscription->starts_on->toDateString())->toBe('2026-01-31')
        ->and($subscription->ends_on->toDateString())->toBe('2026-02-28');
});

it('rejects an inactive plan and logs nothing', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subActionSportFor($tenant);
    $player = subActionPlayerFor($tenant, $sport);
    $plan = subActionPlanFor($tenant, $sport, ['is_active' => false]);

    $error = subActionCatch(fn () => subActionSubscribe($tenant, $player, $plan));

    expect($error?->errorCode)->toBe('player_subscription.plan_inactive')
        ->and(PlayerSubscription::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(Activity::query()->where('event', 'player_subscription.created')->count())->toBe(0);
});

it('rejects a player not enrolled in the plan sport', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subActionSportFor($tenant);
    $player = Player::factory()->create(['club_id' => $tenant->club->id]);
    $plan = subActionPlanFor($tenant, $sport);

    $error = subActionCatch(fn () => subActionSubscribe($tenant, $player, $plan));

    expect($error?->errorCode)->toBe('player_subscription.player_not_enrolled')
        ->and(PlayerSubscription::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('rejects an overlapping subscription to the same plan', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subActionSportFor($tenant);
    $player = subActionPlayerFor($tenant, $sport);
    $plan = subActionPlanFor($tenant, $sport, ['duration_months' => 1]);

    subActionSubscribe($tenant, $player, $plan, '2026-01-01');

    $error = subActionCatch(fn () => subActionSubscribe($tenant, $player, $plan, '2026-01-15'));

    expect($error?->errorCode)->toBe('player_subscription.overlap')
        ->and(PlayerSubscription::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('allows a subscription to a different plan for the same period', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subActionSportFor($tenant);
    $player = subActionPlayerFor($tenant, $sport);
    $first = subActionPlanFor($tenant, $sport, ['duration_months' => 1]);
    $second = subActionPlanFor($tenant, $sport, ['duration_months' => 1]);

    subActionSubscribe($tenant, $player, $first, '2026-01-01');
    $other = subActionSubscribe($tenant, $player, $second, '2026-01-01');

    expect($other->exists)->toBeTrue()
        ->and(PlayerSubscription::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('allows back-to-back periods on the same plan', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subActionSportFor($tenant);
    $player = subActionPlayerFor($tenant, $sport);
    $plan = subActionPlanFor($tenant, $sport, ['duration_months' => 1]);

    subActionSubscribe($tenant, $player, $plan, '2026-01-01');
    $next = subActionSubscribe($tenant, $player, $plan, '2026-02-01');

    expect($next->starts_on->toDateString())->toBe('2026-02-01');
});

it('renews an active subscription from its previous ends_on', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subActionSportFor($tenant);
    $player = subActionPlayerFor($tenant, $sport);
    $plan = subActionPlanFor($tenant, $sport, ['duration_months' => 1]);

    $previous = subActionSubscribe($tenant, $player, $plan, '2026-01-01');

    $renewed = subActionInClub($tenant, fn () => app(RenewPlayerSubscriptionAction::class)->execute(
        $previous,
        new RenewPlayerSubscriptionData,
    ));

    expect($renewed->starts_on->toDateString())->toBe('2026-02-01')
        ->and($renewed->ends_on->toDateString())->toBe('2026-03-01')
        ->and($renewed->renewed_from_id)->toBe($previous->id);

    expect(Activity::query()->where('event', 'player_subscription.renewed')->count())->toBe(1);
});

it('renews an expired subscription from today', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-15 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subActionSportFor($tenant);
    $player = subActionPlayerFor($tenant, $sport);
    $plan = subActionPlanFor($tenant, $sport, ['duration_months' => 1]);

    $previous = subActionSubscribe($tenant, $player, $plan, '2026-01-01');

    $renewed = subActionInClub($tenant, fn () => app(RenewPlayerSubscriptionAction::class)->execute(
        $previous,
        new RenewPlayerSubscriptionData,
    ));

    expect($renewed->starts_on->toDateString())->toBe('2026-03-15')
        ->and($renewed->ends_on->toDateString())->toBe('2026-04-15');
});

it('renews with the current plan price while the old subscription keeps its snapshot', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subActionSportFor($tenant);
    $player = subActionPlayerFor($tenant, $sport);
    $plan = subActionPlanFor($tenant, $sport, ['price_cents' => 50000, 'duration_months' => 1]);

    $previous = subActionSubscribe($tenant, $player, $plan, '2026-01-01');
    $plan->update(['price_cents' => 80000]);

    $renewed = subActionInClub($tenant, fn () => app(RenewPlayerSubscriptionAction::class)->execute(
        $previous,
        new RenewPlayerSubscriptionData,
    ));

    expect($renewed->price_cents)->toBe(80000)
        ->and($renewed->total_cents)->toBe(80000)
        ->and($previous->fresh()->price_cents)->toBe(50000);
});

it('rejects renewing a cancelled subscription', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subActionSportFor($tenant);
    $player = subActionPlayerFor($tenant, $sport);
    $plan = subActionPlanFor($tenant, $sport, ['duration_months' => 1]);

    $previous = subActionInClub($tenant, fn () => PlayerSubscription::factory()->create([
        'club_id' => $tenant->club->id,
        'player_id' => $player->id,
        'membership_plan_id' => $plan->id,
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-02-01',
        'cancelled_at' => now(),
    ]));

    $error = subActionCatch(fn () => subActionInClub($tenant, fn () => app(RenewPlayerSubscriptionAction::class)->execute(
        $previous,
        new RenewPlayerSubscriptionData,
    )));

    expect($error?->errorCode)->toBe('player_subscription.cannot_renew_cancelled')
        ->and(Activity::query()->where('event', 'player_subscription.renewed')->count())->toBe(0);
});

it('cancels an active subscription and records the actor', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subActionSportFor($tenant);
    $player = subActionPlayerFor($tenant, $sport);
    $plan = subActionPlanFor($tenant, $sport, ['duration_months' => 1]);

    $subscription = subActionSubscribe($tenant, $player, $plan, '2026-01-01');

    $cancelled = subActionInClub($tenant, fn () => app(CancelPlayerSubscriptionAction::class)->execute(
        $subscription,
        new CancelPlayerSubscriptionData($tenant->user->id),
    ));

    expect($cancelled->cancelled_at)->not->toBeNull()
        ->and($cancelled->cancelled_by)->toBe($tenant->user->id)
        ->and($cancelled->statusOn('2026-01-10'))->toBe(SubscriptionStatus::Cancelled);

    expect(Activity::query()->where('event', 'player_subscription.cancelled')->count())->toBe(1);
});

it('rejects cancelling an expired subscription', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-01 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subActionSportFor($tenant);
    $player = subActionPlayerFor($tenant, $sport);
    $plan = subActionPlanFor($tenant, $sport, ['duration_months' => 1]);

    $subscription = subActionSubscribe($tenant, $player, $plan, '2026-01-01');

    $error = subActionCatch(fn () => subActionInClub($tenant, fn () => app(CancelPlayerSubscriptionAction::class)->execute(
        $subscription,
        new CancelPlayerSubscriptionData($tenant->user->id),
    )));

    expect($error?->errorCode)->toBe('player_subscription.not_cancellable');
});

it('rejects cancelling an already cancelled subscription', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $sport = subActionSportFor($tenant);
    $player = subActionPlayerFor($tenant, $sport);
    $plan = subActionPlanFor($tenant, $sport, ['duration_months' => 1]);

    $subscription = subActionInClub($tenant, fn () => PlayerSubscription::factory()->create([
        'club_id' => $tenant->club->id,
        'player_id' => $player->id,
        'membership_plan_id' => $plan->id,
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-02-01',
        'cancelled_at' => now(),
    ]));

    $error = subActionCatch(fn () => subActionInClub($tenant, fn () => app(CancelPlayerSubscriptionAction::class)->execute(
        $subscription,
        new CancelPlayerSubscriptionData($tenant->user->id),
    )));

    expect($error?->errorCode)->toBe('player_subscription.already_cancelled');
});

it('blocks deleting a player that has a subscription', function () {
    $tenant = TenantFixture::create();
    $sport = subActionSportFor($tenant);
    $player = subActionPlayerFor($tenant, $sport);
    $plan = subActionPlanFor($tenant, $sport);

    subActionInClub($tenant, fn () => PlayerSubscription::factory()->create([
        'club_id' => $tenant->club->id,
        'player_id' => $player->id,
        'membership_plan_id' => $plan->id,
    ]));

    $error = subActionCatch(fn () => subActionInClub($tenant, fn () => app(DeletePlayerAction::class)->execute($player)));

    expect($error?->errorCode)->toBe('player.has_subscriptions')
        ->and(Player::query()->withoutGlobalScopes()->whereKey($player->id)->exists())->toBeTrue();
});

it('still deletes a player without subscriptions', function () {
    $tenant = TenantFixture::create();
    $sport = subActionSportFor($tenant);
    $player = subActionPlayerFor($tenant, $sport);

    subActionInClub($tenant, fn () => app(DeletePlayerAction::class)->execute($player));

    expect(Player::query()->withoutGlobalScopes()->whereKey($player->id)->exists())->toBeFalse();
});

it('computes expired status when time moves past ends_on without any job', function () {
    $tenant = TenantFixture::create();
    $sport = subActionSportFor($tenant);
    $player = subActionPlayerFor($tenant, $sport);
    $plan = subActionPlanFor($tenant, $sport);

    $subscription = subActionInClub($tenant, fn () => PlayerSubscription::factory()->create([
        'club_id' => $tenant->club->id,
        'player_id' => $player->id,
        'membership_plan_id' => $plan->id,
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-02-01',
    ]));

    expect($subscription->statusOn('2026-01-31'))->toBe(SubscriptionStatus::Active)
        ->and($subscription->statusOn('2026-02-01'))->toBe(SubscriptionStatus::Expired);
});
