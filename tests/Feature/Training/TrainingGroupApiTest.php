<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Domain\Sport\Models\ClubSport;
use App\Domain\Sport\Models\Sport;
use App\Domain\Training\Models\TrainingGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

function groupSportFor(object $tenant, ?Sport $sport = null): Sport
{
    $sport ??= Sport::factory()->create();
    app(CurrentClub::class)->runAs($tenant->club, fn () => ClubSport::query()->create(['sport_id' => $sport->id]));

    return $sport;
}

function groupPayload(Sport $sport, array $overrides = []): array
{
    return array_merge([
        'sport_id' => $sport->id,
        'name' => 'Juniors',
        'coach_id' => null,
        'is_active' => true,
        'schedules' => [
            ['day_of_week' => 1, 'start_time' => '17:00', 'end_time' => '18:30'],
        ],
    ], $overrides);
}

function makeGroupFor(object $tenant, Sport $sport, array $attributes = []): TrainingGroup
{
    return TrainingGroup::factory()->create(array_merge([
        'club_id' => $tenant->club->id,
        'sport_id' => $sport->id,
    ], $attributes));
}

function coachFor(object $tenant, RoleName $role = RoleName::Coach): User
{
    $previous = getPermissionsTeamId();
    setPermissionsTeamId($tenant->club->id);

    $user = User::factory()->create(['club_id' => $tenant->club->id]);
    $user->assignRole($role->value);

    setPermissionsTeamId($previous);

    return $user;
}

it('lets the owner create a group with a schedule and logs it', function () {
    $tenant = TenantFixture::create();
    $sport = groupSportFor($tenant);
    $coach = coachFor($tenant);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/training-groups', groupPayload($sport, ['coach_id' => $coach->id]))
        ->assertCreated()
        ->assertJsonPath('data.name', 'Juniors')
        ->assertJsonPath('data.coach.id', $coach->id)
        ->assertJsonPath('data.sport.slug', $sport->slug)
        ->assertJsonPath('data.schedules.0.day_of_week', 1)
        ->assertJsonPath('data.schedules.0.start_time', '17:00')
        ->assertJsonPath('data.schedules.0.end_time', '18:30');

    expect(Activity::query()->where('event', 'training_group.created')->where('club_id', $tenant->club->id)->count())->toBe(1);
});

it('creates a group without a coach', function () {
    $tenant = TenantFixture::create();
    $sport = groupSportFor($tenant);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/training-groups', groupPayload($sport))
        ->assertCreated()
        ->assertJsonPath('data.coach_id', null);
});

it('lets staff create a group', function () {
    $tenant = TenantFixture::create(RoleName::Staff);
    $sport = groupSportFor($tenant);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/training-groups', groupPayload($sport))
        ->assertCreated();
});

it('forbids a coach from creating or updating a group', function () {
    $tenant = TenantFixture::create(RoleName::Coach);
    $sport = groupSportFor($tenant);
    $group = makeGroupFor($tenant, $sport);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/training-groups', groupPayload($sport))
        ->assertForbidden()
        ->assertJsonPath('code', 'http.forbidden');

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->putJson("/api/v1/training-groups/{$group->id}", [
            'name' => 'X', 'coach_id' => null, 'is_active' => true, 'schedules' => [],
        ])
        ->assertForbidden();
});

it('forbids a referee from reading groups', function () {
    $tenant = TenantFixture::create(RoleName::Referee);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/training-groups')
        ->assertForbidden();
});

it('lets a coach list and read groups', function () {
    $tenant = TenantFixture::create(RoleName::Coach);
    $group = makeGroupFor($tenant, groupSportFor($tenant));

    $this->withToken($tenant->token)
        ->getJson('/api/v1/training-groups')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson("/api/v1/training-groups/{$group->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $group->id);
});

it('rejects a sport the club does not offer', function () {
    $tenant = TenantFixture::create();
    $sport = Sport::factory()->create();

    $this->withToken($tenant->token)
        ->postJson('/api/v1/training-groups', groupPayload($sport))
        ->assertStatus(422)
        ->assertJsonPath('code', 'training_group.sport_not_offered');
});

it('rejects an inactive sport', function () {
    $tenant = TenantFixture::create();
    $sport = groupSportFor($tenant);
    $sport->update(['is_active' => false]);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/training-groups', groupPayload($sport))
        ->assertStatus(422)
        ->assertJsonPath('code', 'sport.inactive');
});

it('rejects a duplicate name for the same sport', function () {
    $tenant = TenantFixture::create();
    $sport = groupSportFor($tenant);
    makeGroupFor($tenant, $sport, ['name' => 'Juniors']);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/training-groups', groupPayload($sport))
        ->assertStatus(422)
        ->assertJsonPath('code', 'training_group.name_taken');
});

it('rejects a coach from another club', function () {
    $tenant = TenantFixture::create();
    $sport = groupSportFor($tenant);
    $outsider = TenantFixture::create(RoleName::Coach);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/training-groups', groupPayload($sport, ['coach_id' => $outsider->user->id]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'training_group.coach_invalid');
});

it('rejects a user of the club who is not a coach', function () {
    $tenant = TenantFixture::create();
    $sport = groupSportFor($tenant);
    $staff = coachFor($tenant, RoleName::Staff);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/training-groups', groupPayload($sport, ['coach_id' => $staff->id]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'training_group.coach_invalid');
});

it('rejects an end time before the start time', function () {
    $tenant = TenantFixture::create();
    $sport = groupSportFor($tenant);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/training-groups', groupPayload($sport, [
            'schedules' => [['day_of_week' => 1, 'start_time' => '17:00', 'end_time' => '16:00']],
        ]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'training_group.schedule_invalid');
});

it('rejects overlapping slots but accepts back-to-back ones', function () {
    $tenant = TenantFixture::create();
    $sport = groupSportFor($tenant);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/training-groups', groupPayload($sport, [
            'schedules' => [
                ['day_of_week' => 1, 'start_time' => '17:00', 'end_time' => '18:30'],
                ['day_of_week' => 1, 'start_time' => '18:00', 'end_time' => '19:00'],
            ],
        ]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'training_group.schedule_overlap');

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson('/api/v1/training-groups', groupPayload($sport, [
            'schedules' => [
                ['day_of_week' => 1, 'start_time' => '17:00', 'end_time' => '18:00'],
                ['day_of_week' => 1, 'start_time' => '18:00', 'end_time' => '19:00'],
            ],
        ]))
        ->assertCreated()
        ->assertJsonCount(2, 'data.schedules');
});

it('validates the payload', function (array $overrides, string $field) {
    $tenant = TenantFixture::create();
    $sport = groupSportFor($tenant);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/training-groups', groupPayload($sport, $overrides))
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation.failed')
        ->assertJsonValidationErrors($field);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'unknown sport' => [['sport_id' => 999999], 'sport_id'],
    'schedules not an array' => [['schedules' => 'x'], 'schedules'],
    'bad day' => [['schedules' => [['day_of_week' => 7, 'start_time' => '17:00', 'end_time' => '18:00']]], 'schedules.0.day_of_week'],
    'bad time' => [['schedules' => [['day_of_week' => 1, 'start_time' => 'abc', 'end_time' => '18:00']]], 'schedules.0.start_time'],
]);

it('updates a group and its schedule and logs the changes', function () {
    $tenant = TenantFixture::create();
    $sport = groupSportFor($tenant);
    $group = makeGroupFor($tenant, $sport, ['name' => 'Juniors']);

    $this->withToken($tenant->token)
        ->putJson("/api/v1/training-groups/{$group->id}", [
            'name' => 'Seniors',
            'coach_id' => null,
            'is_active' => false,
            'schedules' => [['day_of_week' => 3, 'start_time' => '19:00', 'end_time' => '20:00']],
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Seniors')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.sport_id', $sport->id)
        ->assertJsonCount(1, 'data.schedules')
        ->assertJsonPath('data.schedules.0.day_of_week', 3);

    $activity = Activity::query()->where('event', 'training_group.updated')->first();

    expect($activity)->not->toBeNull()
        ->and(array_keys($activity->properties->get('changes')))->toEqualCanonicalizing(['name', 'is_active', 'schedules']);
});

it('does not log a save with no changes', function () {
    $tenant = TenantFixture::create();
    $group = makeGroupFor($tenant, groupSportFor($tenant), ['name' => 'Juniors', 'is_active' => true, 'coach_id' => null]);

    $this->withToken($tenant->token)
        ->putJson("/api/v1/training-groups/{$group->id}", [
            'name' => 'Juniors', 'coach_id' => null, 'is_active' => true, 'schedules' => [],
        ])
        ->assertOk();

    expect(Activity::query()->where('event', 'training_group.updated')->count())->toBe(0);
});

it('does not allow changing the sport of a group', function () {
    $tenant = TenantFixture::create();
    $sport = groupSportFor($tenant);
    $other = groupSportFor($tenant);
    $group = makeGroupFor($tenant, $sport);

    $this->withToken($tenant->token)
        ->putJson("/api/v1/training-groups/{$group->id}", [
            'sport_id' => $other->id, 'name' => 'X', 'coach_id' => null, 'is_active' => true, 'schedules' => [],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('sport_id');

    expect($group->fresh()->sport_id)->toBe($sport->id);
});

it('keeps a coach who lost the coach role as long as the coach is not changed', function () {
    $tenant = TenantFixture::create();
    $sport = groupSportFor($tenant);
    $coach = coachFor($tenant);
    $group = makeGroupFor($tenant, $sport, ['name' => 'Juniors', 'coach_id' => $coach->id]);

    setPermissionsTeamId($tenant->club->id);
    $coach->syncRoles([RoleName::Staff->value]);

    $this->withToken($tenant->token)
        ->putJson("/api/v1/training-groups/{$group->id}", [
            'name' => 'Juniors B', 'coach_id' => $coach->id, 'is_active' => true, 'schedules' => [],
        ])
        ->assertOk()
        ->assertJsonPath('data.coach_id', $coach->id);
});

it('filters the list by sport and active flag', function () {
    $tenant = TenantFixture::create();
    $karate = groupSportFor($tenant);
    $judo = groupSportFor($tenant);
    makeGroupFor($tenant, $karate, ['name' => 'A']);
    makeGroupFor($tenant, $karate, ['name' => 'B', 'is_active' => false]);
    makeGroupFor($tenant, $judo, ['name' => 'C']);

    $this->withToken($tenant->token)
        ->getJson("/api/v1/training-groups?sport_id={$karate->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson('/api/v1/training-groups?is_active=0')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'B');
});

it('does not list or read groups of another club', function () {
    $a = TenantFixture::create();
    $b = TenantFixture::create();
    $group = makeGroupFor($a, groupSportFor($a));

    $this->withToken($b->token)
        ->getJson('/api/v1/training-groups')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->app['auth']->forgetGuards();

    $this->withToken($b->token)
        ->getJson("/api/v1/training-groups/{$group->id}")
        ->assertNotFound();
});

it('cannot update a group of another club', function () {
    $a = TenantFixture::create();
    $b = TenantFixture::create();
    $group = makeGroupFor($a, groupSportFor($a), ['name' => 'Juniors']);

    $this->withToken($b->token)
        ->putJson("/api/v1/training-groups/{$group->id}", [
            'name' => 'Hacked', 'coach_id' => null, 'is_active' => true, 'schedules' => [],
        ])
        ->assertNotFound();

    expect($group->fresh()->name)->toBe('Juniors');
});
