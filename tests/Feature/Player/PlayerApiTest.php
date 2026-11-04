<?php

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Models\Club;
use App\Domain\Player\Models\Player;
use App\Domain\Subscription\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

function validPlayerPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ahmed Ali',
        'national_id' => '31005150101234',
        'phone' => '01012345678',
    ], $overrides);
}

function makePlayerFor(Club $club): Player
{
    return Player::factory()->create(['club_id' => $club->id]);
}

it('lets an owner create a player and derives age data from the national id', function () {
    $tenant = TenantFixture::create(RoleName::Owner);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/players', validPlayerPayload())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Ahmed Ali')
        ->assertJsonPath('data.birth_date', '2010-05-15')
        ->assertJsonPath('data.gender', 'male');
});

it('lets a coach create a player', function () {
    $tenant = TenantFixture::create(RoleName::Coach);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/players', validPlayerPayload())
        ->assertCreated();
});

it('returns 403 before validation for a user without players.manage', function () {
    $tenant = TenantFixture::create(RoleName::Referee);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/players', ['name' => ''])
        ->assertForbidden()
        ->assertJsonPath('code', 'http.forbidden');
});

it('validates the national id', function () {
    $tenant = TenantFixture::create(RoleName::Owner);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/players', validPlayerPayload(['national_id' => '123']))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation.failed')
        ->assertJsonValidationErrors('national_id');
});

it('returns the business error code when birth data conflicts with the national id', function () {
    $tenant = TenantFixture::create(RoleName::Owner);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/players', validPlayerPayload(['birth_date' => '2011-01-01']))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'player.national_id_mismatch');
});

it('returns the plan limit error when the club is full', function () {
    $tenant = TenantFixture::create(RoleName::Owner);
    $plan = Plan::factory()->create(['max_players' => 0]);
    $tenant->club->forceFill(['plan_id' => $plan->id])->save();

    $this->withToken($tenant->token)
        ->postJson('/api/v1/players', validPlayerPayload())
        ->assertForbidden()
        ->assertJsonPath('code', 'plan.players_limit_reached');
});

it('shows a player of the same club', function () {
    $tenant = TenantFixture::create(RoleName::Coach);
    $player = makePlayerFor($tenant->club);

    $this->withToken($tenant->token)
        ->getJson("/api/v1/players/{$player->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $player->id);
});

it('hides a player of another club', function () {
    $mine = TenantFixture::create(RoleName::Owner);
    $other = TenantFixture::create(RoleName::Owner);
    $player = makePlayerFor($other->club);

    $this->withToken($mine->token)
        ->getJson("/api/v1/players/{$player->id}")
        ->assertNotFound();
});

it('forbids viewing players without players.view', function () {
    $tenant = TenantFixture::create(RoleName::Referee);
    $player = makePlayerFor($tenant->club);

    $this->withToken($tenant->token)
        ->getJson("/api/v1/players/{$player->id}")
        ->assertForbidden();
});
