<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Models\Club;
use App\Domain\Player\Enums\Gender;
use App\Domain\Player\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

function seedPlayer(Club $club, array $attributes = []): Player
{
    return Player::factory()->create(array_merge(['club_id' => $club->id], $attributes));
}

/**
 * Ages are relative to today so the tests do not rot: Ahmed 16, Sara 26, Omar 11.
 */
function seedRoster(Club $club): void
{
    seedPlayer($club, [
        'name' => 'Ahmed Ali',
        'gender' => Gender::Male,
        'phone' => '01011111111',
        'birth_date' => now()->subYears(16)->subMonths(2)->toDateString(),
    ]);
    seedPlayer($club, [
        'name' => 'Sara Hassan',
        'gender' => Gender::Female,
        'phone' => '01022222222',
        'birth_date' => now()->subYears(26)->subMonths(2)->toDateString(),
    ]);
    seedPlayer($club, [
        'name' => 'Omar Said',
        'gender' => Gender::Male,
        'phone' => '01033333333',
        'birth_date' => now()->subYears(11)->subMonths(2)->toDateString(),
    ]);
}

function seedExistingAhmed(Club $club): Player
{
    return seedPlayer($club, [
        'name' => 'Ahmed Ali',
        'national_id' => '31005150101234',
        'birth_date' => '2010-05-15',
        'gender' => Gender::Male,
        'phone' => '01012345678',
    ]);
}

// ---- list ----

it('lists only the players of the current club with pagination meta', function () {
    $mine = TenantFixture::create(RoleName::Coach);
    $other = TenantFixture::create(RoleName::Owner);
    seedPlayer($mine->club);
    seedPlayer($mine->club);
    seedPlayer($other->club);

    $this->withToken($mine->token)
        ->getJson('/api/v1/players')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2);
});

it('sorts by name ascending by default', function () {
    $tenant = TenantFixture::create(RoleName::Owner);
    seedRoster($tenant->club);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/players')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Ahmed Ali', 'Omar Said', 'Sara Hassan']);
});

it('sorts by birth date descending', function () {
    $tenant = TenantFixture::create(RoleName::Owner);
    seedRoster($tenant->club);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/players?sort=-birth_date')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Omar Said', 'Ahmed Ali', 'Sara Hassan']);
});

it('filters by gender', function () {
    $tenant = TenantFixture::create(RoleName::Owner);
    seedRoster($tenant->club);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/players?gender=female')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Sara Hassan']);
});

it('filters by age range', function () {
    $tenant = TenantFixture::create(RoleName::Owner);
    seedRoster($tenant->club);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/players?age_min=15&age_max=20')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Ahmed Ali']);
});

it('searches by name', function () {
    $tenant = TenantFixture::create(RoleName::Owner);
    seedRoster($tenant->club);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/players?search=sara')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Sara Hassan']);
});

it('searches by phone prefix', function () {
    $tenant = TenantFixture::create(RoleName::Owner);
    seedRoster($tenant->club);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/players?search=010333')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Omar Said']);
});

it('paginates and caps the page size at 100', function () {
    $tenant = TenantFixture::create(RoleName::Owner);
    seedRoster($tenant->club);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/players?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/players?per_page=500')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

it('rejects an unknown sort column', function () {
    $tenant = TenantFixture::create(RoleName::Owner);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/players?sort=password')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sort');
});

it('forbids listing players without players.view', function () {
    $tenant = TenantFixture::create(RoleName::Referee);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/players')
        ->assertForbidden();
});

// ---- update ----

it('updates a player and logs only the safe fields', function () {
    $tenant = TenantFixture::create(RoleName::Coach);
    $player = seedExistingAhmed($tenant->club);

    $this->withToken($tenant->token)
        ->putJson("/api/v1/players/{$player->id}", [
            'name' => 'Ahmed Mohamed',
            'national_id' => '31005150101234',
            'phone' => '01099999999',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Ahmed Mohamed');

    $activity = Activity::query()->where('event', 'player.updated')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->club_id)->toBe($tenant->club->id)
        ->and($activity->properties->get('changes'))->toEqual([
            'name' => ['from' => 'Ahmed Ali', 'to' => 'Ahmed Mohamed'],
        ])
        ->and($activity->properties->get('sensitive_fields_changed'))->toEqual(['phone'])
        ->and(json_encode($activity->properties))->not->toContain('01099999999')
        ->and(json_encode($activity->properties))->not->toContain('31005150101234');
});

it('does not log an update that changes nothing', function () {
    $tenant = TenantFixture::create(RoleName::Owner);
    $player = seedExistingAhmed($tenant->club);

    $this->withToken($tenant->token)
        ->putJson("/api/v1/players/{$player->id}", [
            'name' => 'Ahmed Ali',
            'national_id' => '31005150101234',
            'phone' => '01012345678',
        ])
        ->assertOk();

    expect(Activity::query()->where('event', 'player.updated')->count())->toBe(0);
});

it('rejects a national id already used by another player of the same club', function () {
    $tenant = TenantFixture::create(RoleName::Owner);
    $player = seedExistingAhmed($tenant->club);
    seedPlayer($tenant->club, ['national_id' => '31005150101245']);

    $this->withToken($tenant->token)
        ->putJson("/api/v1/players/{$player->id}", [
            'name' => 'Ahmed Ali',
            'national_id' => '31005150101245',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'player.national_id_taken');
});

it('returns 404 when updating a player of another club', function () {
    $mine = TenantFixture::create(RoleName::Owner);
    $other = TenantFixture::create(RoleName::Owner);
    $player = seedPlayer($other->club);

    $this->withToken($mine->token)
        ->putJson("/api/v1/players/{$player->id}", ['name' => 'Hacker'])
        ->assertNotFound();
});

it('forbids updating without players.manage', function () {
    $tenant = TenantFixture::create(RoleName::Referee);
    $player = seedPlayer($tenant->club);

    $this->withToken($tenant->token)
        ->putJson("/api/v1/players/{$player->id}", ['name' => ''])
        ->assertForbidden();
});

// ---- delete ----

it('deletes a player and logs it', function () {
    $tenant = TenantFixture::create(RoleName::Owner);
    $player = seedPlayer($tenant->club);

    $this->withToken($tenant->token)
        ->deleteJson("/api/v1/players/{$player->id}")
        ->assertNoContent();

    expect(Player::query()->withoutGlobalScopes()->find($player->id))->toBeNull()
        ->and(Activity::query()->where('event', 'player.deleted')->count())->toBe(1);
});

it('returns 404 when deleting a player of another club', function () {
    $mine = TenantFixture::create(RoleName::Owner);
    $other = TenantFixture::create(RoleName::Owner);
    $player = seedPlayer($other->club);

    $this->withToken($mine->token)
        ->deleteJson("/api/v1/players/{$player->id}")
        ->assertNotFound();

    expect(Player::query()->withoutGlobalScopes()->find($player->id))->not->toBeNull();
});

it('forbids deleting without players.manage', function () {
    $tenant = TenantFixture::create(RoleName::Referee);
    $player = seedPlayer($tenant->club);

    $this->withToken($tenant->token)
        ->deleteJson("/api/v1/players/{$player->id}")
        ->assertForbidden();
});
