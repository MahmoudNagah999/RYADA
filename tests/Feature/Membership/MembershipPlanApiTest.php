<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Membership\Models\MembershipPlan;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Domain\Sport\Models\ClubSport;
use App\Domain\Sport\Models\Sport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

function planSportFor(object $tenant, ?Sport $sport = null): Sport
{
    $sport ??= Sport::factory()->create();
    app(CurrentClub::class)->runAs($tenant->club, fn () => ClubSport::query()->create(['sport_id' => $sport->id]));

    return $sport;
}

function membershipPayload(Sport $sport, array $overrides = []): array
{
    return array_merge([
        'sport_id' => $sport->id,
        'name' => 'Monthly',
        'price_cents' => 50000,
        'duration_months' => 1,
    ], $overrides);
}

function makePlanFor(object $tenant, Sport $sport, array $attributes = []): MembershipPlan
{
    return MembershipPlan::factory()->create(array_merge([
        'club_id' => $tenant->club->id,
        'sport_id' => $sport->id,
    ], $attributes));
}

it('lets the owner create a plan and logs it', function () {
    $tenant = TenantFixture::create();
    $sport = planSportFor($tenant);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/membership-plans', membershipPayload($sport))
        ->assertCreated()
        ->assertJsonPath('data.name', 'Monthly')
        ->assertJsonPath('data.price_cents', 50000)
        ->assertJsonPath('data.currency', 'EGP')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.sport.slug', $sport->slug);

    expect(MembershipPlan::query()->withoutGlobalScopes()->where('club_id', $tenant->club->id)->count())->toBe(1);
    expect(Activity::query()->where('event', 'membership_plan.created')->where('club_id', $tenant->club->id)->count())->toBe(1);
});

it('lets staff create a plan', function () {
    $tenant = TenantFixture::create(RoleName::Staff);
    $sport = planSportFor($tenant);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/membership-plans', membershipPayload($sport))
        ->assertCreated();
});

it('forbids a coach from creating or updating a plan', function () {
    $tenant = TenantFixture::create(RoleName::Coach);
    $sport = planSportFor($tenant);
    $plan = makePlanFor($tenant, $sport);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/membership-plans', membershipPayload($sport))
        ->assertForbidden()
        ->assertJsonPath('code', 'http.forbidden');

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->putJson("/api/v1/membership-plans/{$plan->id}", [
            'name' => 'X', 'price_cents' => 1, 'duration_months' => 1, 'is_active' => true,
        ])
        ->assertForbidden();
});

it('forbids a referee from reading plans', function () {
    $tenant = TenantFixture::create(RoleName::Referee);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/membership-plans')
        ->assertForbidden();
});

it('lets a coach list and read plans', function () {
    $tenant = TenantFixture::create(RoleName::Coach);
    $plan = makePlanFor($tenant, planSportFor($tenant));

    $this->withToken($tenant->token)
        ->getJson('/api/v1/membership-plans')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson("/api/v1/membership-plans/{$plan->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $plan->id);
});

it('rejects a sport the club does not offer', function () {
    $tenant = TenantFixture::create();
    $sport = Sport::factory()->create();

    $this->withToken($tenant->token)
        ->postJson('/api/v1/membership-plans', membershipPayload($sport))
        ->assertStatus(422)
        ->assertJsonPath('code', 'membership_plan.sport_not_offered');
});

it('rejects an inactive sport', function () {
    $tenant = TenantFixture::create();
    $sport = planSportFor($tenant);
    $sport->update(['is_active' => false]);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/membership-plans', membershipPayload($sport))
        ->assertStatus(422)
        ->assertJsonPath('code', 'sport.inactive');
});

it('rejects a duplicate name for the same sport', function () {
    $tenant = TenantFixture::create();
    $sport = planSportFor($tenant);
    makePlanFor($tenant, $sport, ['name' => 'Monthly']);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/membership-plans', membershipPayload($sport))
        ->assertStatus(422)
        ->assertJsonPath('code', 'membership_plan.name_taken');
});

it('validates the payload', function (array $overrides, string $field) {
    $tenant = TenantFixture::create();
    $sport = planSportFor($tenant);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/membership-plans', membershipPayload($sport, $overrides))
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation.failed')
        ->assertJsonValidationErrors($field);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'negative price' => [['price_cents' => -1], 'price_cents'],
    'zero duration' => [['duration_months' => 0], 'duration_months'],
    'duration too long' => [['duration_months' => 61], 'duration_months'],
    'unknown sport' => [['sport_id' => 999999], 'sport_id'],
]);

it('updates a plan and logs the change', function () {
    $tenant = TenantFixture::create();
    $sport = planSportFor($tenant);
    $plan = makePlanFor($tenant, $sport, ['name' => 'Monthly', 'price_cents' => 50000, 'duration_months' => 1, 'is_active' => true]);

    $this->withToken($tenant->token)
        ->putJson("/api/v1/membership-plans/{$plan->id}", [
            'name' => 'Monthly', 'price_cents' => 60000, 'duration_months' => 1, 'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.price_cents', 60000)
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.sport_id', $sport->id);

    expect(Activity::query()->where('event', 'membership_plan.updated')->count())->toBe(1);
});

it('does not allow changing the sport of a plan', function () {
    $tenant = TenantFixture::create();
    $sport = planSportFor($tenant);
    $other = planSportFor($tenant);
    $plan = makePlanFor($tenant, $sport);

    $this->withToken($tenant->token)
        ->putJson("/api/v1/membership-plans/{$plan->id}", [
            'sport_id' => $other->id, 'name' => 'X', 'price_cents' => 1, 'duration_months' => 1, 'is_active' => true,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('sport_id');

    expect($plan->fresh()->sport_id)->toBe($sport->id);
});

it('filters the list by sport and active flag', function () {
    $tenant = TenantFixture::create();
    $karate = planSportFor($tenant);
    $judo = planSportFor($tenant);
    makePlanFor($tenant, $karate, ['name' => 'A']);
    makePlanFor($tenant, $karate, ['name' => 'B', 'is_active' => false]);
    makePlanFor($tenant, $judo, ['name' => 'C']);

    $this->withToken($tenant->token)
        ->getJson("/api/v1/membership-plans?sport_id={$karate->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson('/api/v1/membership-plans?is_active=0')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'B');
});

it('does not list or read plans of another club', function () {
    $a = TenantFixture::create();
    $b = TenantFixture::create();
    $plan = makePlanFor($a, planSportFor($a));

    $this->withToken($b->token)
        ->getJson('/api/v1/membership-plans')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->app['auth']->forgetGuards();

    $this->withToken($b->token)
        ->getJson("/api/v1/membership-plans/{$plan->id}")
        ->assertNotFound();
});

it('cannot update a plan of another club', function () {
    $a = TenantFixture::create();
    $b = TenantFixture::create();
    $plan = makePlanFor($a, planSportFor($a), ['price_cents' => 50000]);

    $this->withToken($b->token)
        ->putJson("/api/v1/membership-plans/{$plan->id}", [
            'name' => 'Hacked', 'price_cents' => 1, 'duration_months' => 1, 'is_active' => true,
        ])
        ->assertNotFound();

    expect($plan->fresh()->price_cents)->toBe(50000);
});
