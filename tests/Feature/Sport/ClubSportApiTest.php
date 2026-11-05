<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Domain\Sport\Models\ClubSport;
use App\Domain\Sport\Models\Sport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

function attachSportFor(object $tenant, Sport $sport): void
{
    app(CurrentClub::class)->runAs($tenant->club, fn () => ClubSport::query()->create(['sport_id' => $sport->id]));
}

it('lets the owner add a sport and logs it', function () {
    $tenant = TenantFixture::create();
    $sport = Sport::factory()->create(['slug' => 'archery']);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/club/sports', ['sport_id' => $sport->id])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'archery');

    expect(ClubSport::query()->withoutGlobalScopes()->where('club_id', $tenant->club->id)->count())->toBe(1);
    expect(Activity::query()->where('event', 'club.sport_added')->where('club_id', $tenant->club->id)->count())->toBe(1);
});

it('is idempotent when the sport is already offered', function () {
    $tenant = TenantFixture::create();
    $sport = Sport::factory()->create();
    attachSportFor($tenant, $sport);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/club/sports', ['sport_id' => $sport->id])
        ->assertOk();

    expect(Activity::query()->where('event', 'club.sport_added')->count())->toBe(0);
});

it('rejects an inactive sport', function () {
    $tenant = TenantFixture::create();
    $sport = Sport::factory()->inactive()->create();

    $this->withToken($tenant->token)
        ->postJson('/api/v1/club/sports', ['sport_id' => $sport->id])
        ->assertStatus(422)
        ->assertJsonPath('code', 'sport.inactive');
});

it('rejects an unknown sport', function () {
    $tenant = TenantFixture::create();

    $this->withToken($tenant->token)
        ->postJson('/api/v1/club/sports', ['sport_id' => 9999])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation.failed');
});

it('forbids a coach from adding a sport', function () {
    $tenant = TenantFixture::create(RoleName::Coach);
    $sport = Sport::factory()->create();

    $this->withToken($tenant->token)
        ->postJson('/api/v1/club/sports', ['sport_id' => $sport->id])
        ->assertForbidden()
        ->assertJsonPath('code', 'http.forbidden');
});

it('lets a coach read the club sports', function () {
    $tenant = TenantFixture::create(RoleName::Coach);
    attachSportFor($tenant, Sport::factory()->create(['slug' => 'archery']));

    $this->withToken($tenant->token)
        ->getJson('/api/v1/club/sports')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'archery');
});

it('does not show another club its sports', function () {
    $a = TenantFixture::create();
    $b = TenantFixture::create();
    attachSportFor($a, Sport::factory()->create());

    $this->withToken($b->token)
        ->getJson('/api/v1/club/sports')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('removes a sport and logs it', function () {
    $tenant = TenantFixture::create();
    $sport = Sport::factory()->create();
    attachSportFor($tenant, $sport);

    $this->withToken($tenant->token)
        ->deleteJson("/api/v1/club/sports/{$sport->id}")
        ->assertNoContent();

    expect(ClubSport::query()->withoutGlobalScopes()->count())->toBe(0);
    expect(Activity::query()->where('event', 'club.sport_removed')->count())->toBe(1);
});

it('cannot remove a sport that belongs to another club', function () {
    $a = TenantFixture::create();
    $b = TenantFixture::create();
    $sport = Sport::factory()->create();
    attachSportFor($a, $sport);

    $this->withToken($b->token)
        ->deleteJson("/api/v1/club/sports/{$sport->id}")
        ->assertNotFound();

    expect(ClubSport::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('lists only active sports in the catalog', function () {
    $tenant = TenantFixture::create();
    Sport::factory()->create(['slug' => 'archery']);
    Sport::factory()->inactive()->create(['slug' => 'hidden']);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/sports')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'archery');
});
