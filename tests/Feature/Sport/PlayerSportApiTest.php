<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Player\Models\Player;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Domain\Sport\Models\ClubSport;
use App\Domain\Sport\Models\PlayerSport;
use App\Domain\Sport\Models\Sport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

function offerSport(object $tenant, ?Sport $sport = null): Sport
{
    $sport ??= Sport::factory()->create();
    app(CurrentClub::class)->runAs($tenant->club, fn () => ClubSport::query()->create(['sport_id' => $sport->id]));

    return $sport;
}

function playerOf(object $tenant): Player
{
    return Player::factory()->create(['club_id' => $tenant->club->id]);
}

function enrollDirectly(object $tenant, Player $player, Sport $sport): void
{
    app(CurrentClub::class)->runAs($tenant->club, fn () => PlayerSport::query()->create([
        'player_id' => $player->id,
        'sport_id' => $sport->id,
    ]));
}

it('enrolls a player in an offered sport and logs it', function () {
    $tenant = TenantFixture::create();
    $sport = offerSport($tenant);
    $player = playerOf($tenant);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/players/{$player->id}/sports", ['sport_id' => $sport->id])
        ->assertCreated()
        ->assertJsonPath('data.slug', $sport->slug);

    expect(PlayerSport::query()->withoutGlobalScopes()->where('club_id', $tenant->club->id)->count())->toBe(1);
    expect(Activity::query()->where('event', 'player.sport_enrolled')->count())->toBe(1);
});

it('is idempotent when already enrolled', function () {
    $tenant = TenantFixture::create();
    $sport = offerSport($tenant);
    $player = playerOf($tenant);
    enrollDirectly($tenant, $player, $sport);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/players/{$player->id}/sports", ['sport_id' => $sport->id])
        ->assertOk();

    expect(Activity::query()->where('event', 'player.sport_enrolled')->count())->toBe(0);
});

it('rejects a sport the club does not offer', function () {
    $tenant = TenantFixture::create();
    $sport = Sport::factory()->create();
    $player = playerOf($tenant);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/players/{$player->id}/sports", ['sport_id' => $sport->id])
        ->assertStatus(422)
        ->assertJsonPath('code', 'player.sport_not_offered');
});

it('rejects enrolling in a sport that became inactive', function () {
    $tenant = TenantFixture::create();
    $sport = offerSport($tenant);
    $sport->update(['is_active' => false]);
    $player = playerOf($tenant);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/players/{$player->id}/sports", ['sport_id' => $sport->id])
        ->assertStatus(422)
        ->assertJsonPath('code', 'sport.inactive');
});

it('forbids a referee from enrolling', function () {
    $tenant = TenantFixture::create(RoleName::Referee);
    $sport = offerSport($tenant);
    $player = playerOf($tenant);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/players/{$player->id}/sports", ['sport_id' => $sport->id])
        ->assertForbidden();
});

it('cannot enroll a player of another club', function () {
    $a = TenantFixture::create();
    $b = TenantFixture::create();
    $sport = offerSport($b);
    $player = playerOf($a);

    $this->withToken($b->token)
        ->postJson("/api/v1/players/{$player->id}/sports", ['sport_id' => $sport->id])
        ->assertNotFound();
});

it('lists a player enrollments', function () {
    $tenant = TenantFixture::create(RoleName::Coach);
    $sport = offerSport($tenant);
    $player = playerOf($tenant);
    enrollDirectly($tenant, $player, $sport);

    $this->withToken($tenant->token)
        ->getJson("/api/v1/players/{$player->id}/sports")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', $sport->slug);
});

it('does not list enrollments of another club player', function () {
    $a = TenantFixture::create();
    $b = TenantFixture::create();
    $player = playerOf($a);

    $this->withToken($b->token)
        ->getJson("/api/v1/players/{$player->id}/sports")
        ->assertNotFound();
});

it('unenrolls a player and logs it', function () {
    $tenant = TenantFixture::create();
    $sport = offerSport($tenant);
    $player = playerOf($tenant);
    enrollDirectly($tenant, $player, $sport);

    $this->withToken($tenant->token)
        ->deleteJson("/api/v1/players/{$player->id}/sports/{$sport->id}")
        ->assertNoContent();

    expect(PlayerSport::query()->withoutGlobalScopes()->count())->toBe(0);
    expect(Activity::query()->where('event', 'player.sport_unenrolled')->count())->toBe(1);
});

it('returns 404 when unenrolling a sport the player is not in', function () {
    $tenant = TenantFixture::create();
    $sport = offerSport($tenant);
    $player = playerOf($tenant);

    $this->withToken($tenant->token)
        ->deleteJson("/api/v1/players/{$player->id}/sports/{$sport->id}")
        ->assertNotFound();
});

it('blocks removing a club sport while players are enrolled', function () {
    $tenant = TenantFixture::create();
    $sport = offerSport($tenant);
    enrollDirectly($tenant, playerOf($tenant), $sport);

    $this->withToken($tenant->token)
        ->deleteJson("/api/v1/club/sports/{$sport->id}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'player.sport_has_enrollments');

    expect(ClubSport::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('allows removing the club sport once nobody is enrolled', function () {
    $tenant = TenantFixture::create();
    $sport = offerSport($tenant);

    $this->withToken($tenant->token)
        ->deleteJson("/api/v1/club/sports/{$sport->id}")
        ->assertNoContent();
});

it('deletes enrollments when the player is deleted', function () {
    $tenant = TenantFixture::create();
    $sport = offerSport($tenant);
    $player = playerOf($tenant);
    enrollDirectly($tenant, $player, $sport);

    $this->withToken($tenant->token)
        ->deleteJson("/api/v1/players/{$player->id}")
        ->assertNoContent();

    expect(PlayerSport::query()->withoutGlobalScopes()->count())->toBe(0);
});
