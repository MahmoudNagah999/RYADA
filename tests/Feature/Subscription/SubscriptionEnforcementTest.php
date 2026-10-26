<?php

use App\Domain\Club\Actions\SuspendClubAction;
use App\Domain\Subscription\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['auth:sanctum', 'tenant', 'subscription'])
        ->get('api/v1/_subscription-probe', fn () => response()->json(['ok' => true]));
});

it('lets a club without a plan through', function () {
    $tenant = TenantFixture::create();

    $this->withToken($tenant->token)
        ->getJson('/api/v1/_subscription-probe')
        ->assertOk();
});

it('lets a club with a running subscription through', function () {
    $tenant = TenantFixture::create();
    $tenant->club->plan()->associate(Plan::factory()->create());
    $tenant->club->forceFill(['subscription_ends_at' => now()->addMonth()])->save();

    $this->withToken($tenant->token)
        ->getJson('/api/v1/_subscription-probe')
        ->assertOk();
});

it('returns 403 club.suspended for a suspended club', function () {
    $tenant = TenantFixture::create();
    app(SuspendClubAction::class)->execute($tenant->club);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/_subscription-probe')
        ->assertForbidden()
        ->assertJsonPath('code', 'club.suspended');
});

it('returns 402 subscription.expired when the subscription has ended', function () {
    $tenant = TenantFixture::create();
    $tenant->club->forceFill(['subscription_ends_at' => now()->subDay()])->save();

    $this->withToken($tenant->token)
        ->getJson('/api/v1/_subscription-probe')
        ->assertStatus(402)
        ->assertJsonPath('code', 'subscription.expired');
});

it('reports suspension before expiry when both apply', function () {
    $tenant = TenantFixture::create();
    $tenant->club->forceFill(['subscription_ends_at' => now()->subDay()])->save();
    app(SuspendClubAction::class)->execute($tenant->club);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/_subscription-probe')
        ->assertForbidden()
        ->assertJsonPath('code', 'club.suspended');
});

it('translates the error message', function () {
    $tenant = TenantFixture::create();
    app(SuspendClubAction::class)->execute($tenant->club);

    $this->withToken($tenant->token)
        ->withHeader('Accept-Language', 'ar')
        ->getJson('/api/v1/_subscription-probe')
        ->assertJsonPath('message', trans('errors.club.suspended', [], 'ar'));
});

it('does not block a club because another club is suspended', function () {
    $healthy = TenantFixture::create();
    $suspended = TenantFixture::create();
    app(SuspendClubAction::class)->execute($suspended->club);

    $this->withToken($healthy->token)
        ->getJson('/api/v1/_subscription-probe')
        ->assertOk();
});

it('still lets a suspended club read its own profile', function () {
    $tenant = TenantFixture::create();
    app(SuspendClubAction::class)->execute($tenant->club);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/auth/me')
        ->assertOk();
});
