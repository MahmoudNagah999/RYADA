<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Player\Models\Player;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Domain\Sport\Models\ClubSport;
use App\Domain\Sport\Models\PlayerSport;
use App\Domain\Sport\Models\Sport;
use App\Domain\Training\Models\TrainingGroup;
use App\Domain\Training\Models\TrainingGroupPlayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

function memberSportFor(object $tenant): Sport
{
    $sport = Sport::factory()->create();
    app(CurrentClub::class)->runAs($tenant->club, fn () => ClubSport::query()->create(['sport_id' => $sport->id]));

    return $sport;
}

function memberGroupFor(object $tenant, Sport $sport, array $attributes = []): TrainingGroup
{
    return TrainingGroup::factory()->create(array_merge([
        'club_id' => $tenant->club->id,
        'sport_id' => $sport->id,
    ], $attributes));
}

function memberPlayerFor(object $tenant, ?Sport $enrolledIn = null, array $attributes = []): Player
{
    $player = Player::factory()->create(array_merge(['club_id' => $tenant->club->id], $attributes));

    if ($enrolledIn !== null) {
        app(CurrentClub::class)->runAs($tenant->club, fn () => PlayerSport::query()->create([
            'player_id' => $player->id,
            'sport_id' => $enrolledIn->id,
        ]));
    }

    return $player;
}

function addToGroup(TrainingGroup $group, Player $player): void
{
    app(CurrentClub::class)->runAs($group->club, fn () => TrainingGroupPlayer::query()->create([
        'training_group_id' => $group->id,
        'player_id' => $player->id,
    ]));
}

it('adds an enrolled player to a group and logs it', function () {
    $tenant = TenantFixture::create();
    $sport = memberSportFor($tenant);
    $group = memberGroupFor($tenant, $sport);
    $player = memberPlayerFor($tenant, $sport);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/training-groups/{$group->id}/players", ['player_id' => $player->id])
        ->assertCreated()
        ->assertJsonPath('data.player_id', $player->id)
        ->assertJsonPath('data.name', $player->name);

    expect(Activity::query()->where('event', 'training_group.player_added')->where('club_id', $tenant->club->id)->count())->toBe(1);
});

it('is idempotent when the player is already in the group', function () {
    $tenant = TenantFixture::create();
    $sport = memberSportFor($tenant);
    $group = memberGroupFor($tenant, $sport);
    $player = memberPlayerFor($tenant, $sport);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/training-groups/{$group->id}/players", ['player_id' => $player->id])
        ->assertCreated();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson("/api/v1/training-groups/{$group->id}/players", ['player_id' => $player->id])
        ->assertOk();

    expect(TrainingGroupPlayer::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and(Activity::query()->where('event', 'training_group.player_added')->count())->toBe(1);
});

it('rejects a player who is not enrolled in the sport of the group', function () {
    $tenant = TenantFixture::create();
    $sport = memberSportFor($tenant);
    $group = memberGroupFor($tenant, $sport);
    $player = memberPlayerFor($tenant);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/training-groups/{$group->id}/players", ['player_id' => $player->id])
        ->assertStatus(422)
        ->assertJsonPath('code', 'training_group.player_not_enrolled');
});

it('rejects adding a player to an inactive group', function () {
    $tenant = TenantFixture::create();
    $sport = memberSportFor($tenant);
    $group = memberGroupFor($tenant, $sport, ['is_active' => false]);
    $player = memberPlayerFor($tenant, $sport);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/training-groups/{$group->id}/players", ['player_id' => $player->id])
        ->assertStatus(422)
        ->assertJsonPath('code', 'training_group.inactive');
});

it('treats a player of another club like a player that does not exist', function () {
    $tenant = TenantFixture::create();
    $other = TenantFixture::create();
    $sport = memberSportFor($tenant);
    $group = memberGroupFor($tenant, $sport);
    $outsider = memberPlayerFor($other);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/training-groups/{$group->id}/players", ['player_id' => $outsider->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('player_id');
});

it('lets a coach list the players of a group but not change them', function () {
    $tenant = TenantFixture::create(RoleName::Coach);
    $sport = memberSportFor($tenant);
    $group = memberGroupFor($tenant, $sport);
    $player = memberPlayerFor($tenant, $sport);
    addToGroup($group, $player);

    $this->withToken($tenant->token)
        ->getJson("/api/v1/training-groups/{$group->id}/players")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson("/api/v1/training-groups/{$group->id}/players", ['player_id' => $player->id])
        ->assertForbidden();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->deleteJson("/api/v1/training-groups/{$group->id}/players/{$player->id}")
        ->assertForbidden();
});

it('lists the players of a group sorted by name', function () {
    $tenant = TenantFixture::create();
    $sport = memberSportFor($tenant);
    $group = memberGroupFor($tenant, $sport);
    addToGroup($group, memberPlayerFor($tenant, $sport, ['name' => 'Zeyad']));
    addToGroup($group, memberPlayerFor($tenant, $sport, ['name' => 'Ahmed']));

    $this->withToken($tenant->token)
        ->getJson("/api/v1/training-groups/{$group->id}/players")
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Ahmed')
        ->assertJsonPath('data.1.name', 'Zeyad');
});

it('removes a player from a group and logs it', function () {
    $tenant = TenantFixture::create();
    $sport = memberSportFor($tenant);
    $group = memberGroupFor($tenant, $sport);
    $player = memberPlayerFor($tenant, $sport);
    addToGroup($group, $player);

    $this->withToken($tenant->token)
        ->deleteJson("/api/v1/training-groups/{$group->id}/players/{$player->id}")
        ->assertNoContent();

    expect(TrainingGroupPlayer::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(Activity::query()->where('event', 'training_group.player_removed')->count())->toBe(1);
});

it('answers 404 when removing a player who is not in the group', function () {
    $tenant = TenantFixture::create();
    $sport = memberSportFor($tenant);
    $group = memberGroupFor($tenant, $sport);
    $player = memberPlayerFor($tenant, $sport);

    $this->withToken($tenant->token)
        ->deleteJson("/api/v1/training-groups/{$group->id}/players/{$player->id}")
        ->assertNotFound();
});

it('does not expose or change the players of another club group', function () {
    $a = TenantFixture::create();
    $b = TenantFixture::create();
    $sport = memberSportFor($a);
    $group = memberGroupFor($a, $sport);
    $own = memberPlayerFor($b, memberSportFor($b));

    $this->withToken($b->token)
        ->getJson("/api/v1/training-groups/{$group->id}/players")
        ->assertNotFound();

    $this->app['auth']->forgetGuards();

    $this->withToken($b->token)
        ->postJson("/api/v1/training-groups/{$group->id}/players", ['player_id' => $own->id])
        ->assertNotFound();
});

it('blocks unenrolling a player who is still in a group of that sport', function () {
    $tenant = TenantFixture::create();
    $sport = memberSportFor($tenant);
    $group = memberGroupFor($tenant, $sport);
    $player = memberPlayerFor($tenant, $sport);
    addToGroup($group, $player);

    $this->withToken($tenant->token)
        ->deleteJson("/api/v1/players/{$player->id}/sports/{$sport->id}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'player.sport_has_groups');

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->deleteJson("/api/v1/training-groups/{$group->id}/players/{$player->id}")
        ->assertNoContent();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->deleteJson("/api/v1/players/{$player->id}/sports/{$sport->id}")
        ->assertNoContent();
});

it('blocks removing a sport that has an active training group', function () {
    $tenant = TenantFixture::create();
    $sport = memberSportFor($tenant);
    memberGroupFor($tenant, $sport);

    $this->withToken($tenant->token)
        ->deleteJson("/api/v1/club/sports/{$sport->id}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'training_group.sport_has_groups');

    expect(ClubSport::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('allows removing a sport whose groups are all inactive', function () {
    $tenant = TenantFixture::create();
    $sport = memberSportFor($tenant);
    memberGroupFor($tenant, $sport, ['is_active' => false]);

    $this->withToken($tenant->token)
        ->deleteJson("/api/v1/club/sports/{$sport->id}")
        ->assertNoContent();
});

it('ignores active groups of another club when removing a sport', function () {
    $tenant = TenantFixture::create();
    $other = TenantFixture::create();
    $sport = memberSportFor($tenant);
    memberGroupFor($other, $sport);

    $this->withToken($tenant->token)
        ->deleteJson("/api/v1/club/sports/{$sport->id}")
        ->assertNoContent();
});
