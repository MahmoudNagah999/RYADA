<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Player\Models\Player;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Domain\Sport\Models\ClubSport;
use App\Domain\Sport\Models\PlayerSport;
use App\Domain\Sport\Models\Sport;
use App\Domain\Training\Enums\AttendanceStatus;
use App\Domain\Training\Models\TrainingAttendance;
use App\Domain\Training\Models\TrainingGroup;
use App\Domain\Training\Models\TrainingGroupPlayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

function attGroupFor(object $tenant, array $attributes = []): TrainingGroup
{
    $sport = Sport::factory()->create();
    app(CurrentClub::class)->runAs($tenant->club, fn () => ClubSport::query()->create(['sport_id' => $sport->id]));

    return TrainingGroup::factory()->create(array_merge([
        'club_id' => $tenant->club->id,
        'sport_id' => $sport->id,
    ], $attributes));
}

function attMember(object $tenant, TrainingGroup $group, array $attributes = []): Player
{
    $player = Player::factory()->create(array_merge(['club_id' => $tenant->club->id], $attributes));

    app(CurrentClub::class)->runAs($tenant->club, function () use ($player, $group) {
        PlayerSport::query()->create(['player_id' => $player->id, 'sport_id' => $group->sport_id]);
        TrainingGroupPlayer::query()->create(['training_group_id' => $group->id, 'player_id' => $player->id]);
    });

    return $player;
}

function attToday(): string
{
    return Carbon::now(config('attendance.timezone'))->toDateString();
}

function attPayload(string $date, array $records): array
{
    return ['date' => $date, 'records' => $records];
}

it('records attendance for the players of a group and logs it once', function () {
    $tenant = TenantFixture::create();
    $group = attGroupFor($tenant);
    $ahmed = attMember($tenant, $group, ['name' => 'Ahmed']);
    $zeyad = attMember($tenant, $group, ['name' => 'Zeyad']);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/training-groups/{$group->id}/attendance", attPayload(attToday(), [
            ['player_id' => $ahmed->id, 'status' => 'present'],
            ['player_id' => $zeyad->id, 'status' => 'excused'],
        ]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Ahmed')
        ->assertJsonPath('data.0.status', 'present')
        ->assertJsonPath('data.1.name', 'Zeyad')
        ->assertJsonPath('data.1.status', 'excused');

    $logs = Activity::query()->where('event', 'training_group.attendance_recorded')->get();

    expect(TrainingAttendance::query()->withoutGlobalScopes()->count())->toBe(2)
        ->and($logs)->toHaveCount(1)
        ->and($logs->first()->properties->get('created'))->toBe(2);
});

it('updates a recorded date instead of duplicating it, and does not log an unchanged save', function () {
    $tenant = TenantFixture::create();
    $group = attGroupFor($tenant);
    $player = attMember($tenant, $group);
    $url = "/api/v1/training-groups/{$group->id}/attendance";

    $this->withToken($tenant->token)
        ->postJson($url, attPayload(attToday(), [['player_id' => $player->id, 'status' => 'present']]))
        ->assertOk();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson($url, attPayload(attToday(), [['player_id' => $player->id, 'status' => 'absent']]))
        ->assertOk()
        ->assertJsonPath('data.0.status', 'absent');

    expect(TrainingAttendance::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and(Activity::query()->where('event', 'training_group.attendance_recorded')->count())->toBe(2);

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson($url, attPayload(attToday(), [['player_id' => $player->id, 'status' => 'absent']]))
        ->assertOk();

    expect(Activity::query()->where('event', 'training_group.attendance_recorded')->count())->toBe(2);
});

it('allows a past date (makeup session)', function () {
    $tenant = TenantFixture::create();
    $group = attGroupFor($tenant);
    $player = attMember($tenant, $group);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/training-groups/{$group->id}/attendance", attPayload('2026-01-15', [
            ['player_id' => $player->id, 'status' => 'present'],
        ]))
        ->assertOk();
});

it('judges "today" in the club timezone, not in UTC', function () {
    // 22:30 UTC on Nov 7 is already 00:30 on Nov 8 in Cairo.
    Carbon::setTestNow(Carbon::parse('2026-11-07 22:30:00', 'UTC'));

    $tenant = TenantFixture::create();
    $group = attGroupFor($tenant);
    $player = attMember($tenant, $group);
    $url = "/api/v1/training-groups/{$group->id}/attendance";
    $records = [['player_id' => $player->id, 'status' => 'present']];

    $this->withToken($tenant->token)
        ->postJson($url, attPayload('2026-11-08', $records))
        ->assertOk();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson($url, attPayload('2026-11-09', $records))
        ->assertStatus(422)
        ->assertJsonPath('code', 'attendance.date_in_future');
});

it('rejects a player who is not a member of the group and writes nothing', function () {
    $tenant = TenantFixture::create();
    $group = attGroupFor($tenant);
    $member = attMember($tenant, $group);
    $stranger = attMember($tenant, attGroupFor($tenant));

    $this->withToken($tenant->token)
        ->postJson("/api/v1/training-groups/{$group->id}/attendance", attPayload(attToday(), [
            ['player_id' => $member->id, 'status' => 'present'],
            ['player_id' => $stranger->id, 'status' => 'present'],
        ]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'attendance.player_not_in_group');

    expect(TrainingAttendance::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('rejects recording attendance for an inactive group', function () {
    $tenant = TenantFixture::create();
    $group = attGroupFor($tenant, ['is_active' => false]);
    $player = attMember($tenant, $group);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/training-groups/{$group->id}/attendance", attPayload(attToday(), [
            ['player_id' => $player->id, 'status' => 'present'],
        ]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'training_group.inactive');
});

it('validates the payload', function (array $overrides, string $field) {
    $tenant = TenantFixture::create();
    $group = attGroupFor($tenant);

    $payload = array_merge(
        attPayload(attToday(), [['player_id' => 1, 'status' => 'present']]),
        $overrides,
    );

    $this->withToken($tenant->token)
        ->postJson("/api/v1/training-groups/{$group->id}/attendance", $payload)
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation.failed')
        ->assertJsonValidationErrors($field);
})->with([
    'bad date format' => [['date' => '07/11/2026'], 'date'],
    'impossible date' => [['date' => '2026-02-31'], 'date'],
    'no records' => [['records' => []], 'records'],
    'records not an array' => [['records' => 'x'], 'records'],
    'unknown status' => [['records' => [['player_id' => 1, 'status' => 'late']]], 'records.0.status'],
    'missing player' => [['records' => [['status' => 'present']]], 'records.0.player_id'],
    'duplicate players' => [['records' => [
        ['player_id' => 1, 'status' => 'present'],
        ['player_id' => 1, 'status' => 'absent'],
    ]], 'records.0.player_id'],
]);

it('lets a coach record attendance of their own group but not of another group', function () {
    $tenant = TenantFixture::create(RoleName::Coach);
    $own = attGroupFor($tenant, ['coach_id' => $tenant->user->id]);
    $other = attGroupFor($tenant);
    $player = attMember($tenant, $own);
    $stranger = attMember($tenant, $other);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/training-groups/{$own->id}/attendance", attPayload(attToday(), [
            ['player_id' => $player->id, 'status' => 'present'],
        ]))
        ->assertOk();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson("/api/v1/training-groups/{$other->id}/attendance", attPayload(attToday(), [
            ['player_id' => $stranger->id, 'status' => 'present'],
        ]))
        ->assertForbidden()
        ->assertJsonPath('code', 'http.forbidden');
});

it('lets staff record attendance of any group', function () {
    $tenant = TenantFixture::create(RoleName::Staff);
    $group = attGroupFor($tenant);
    $player = attMember($tenant, $group);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/training-groups/{$group->id}/attendance", attPayload(attToday(), [
            ['player_id' => $player->id, 'status' => 'present'],
        ]))
        ->assertOk();
});

it('forbids a referee from attendance', function () {
    $tenant = TenantFixture::create(RoleName::Referee);
    $group = attGroupFor($tenant);

    $this->withToken($tenant->token)
        ->getJson("/api/v1/training-groups/{$group->id}/attendance?date=".attToday())
        ->assertForbidden();
});

it('shows the attendance sheet of a date with unrecorded members as null', function () {
    $tenant = TenantFixture::create();
    $group = attGroupFor($tenant);
    $ahmed = attMember($tenant, $group, ['name' => 'Ahmed']);
    attMember($tenant, $group, ['name' => 'Zeyad']);

    TrainingAttendance::factory()->create([
        'club_id' => $tenant->club->id,
        'training_group_id' => $group->id,
        'player_id' => $ahmed->id,
        'date' => '2026-11-01',
        'status' => AttendanceStatus::Absent,
    ]);

    $this->withToken($tenant->token)
        ->getJson("/api/v1/training-groups/{$group->id}/attendance?date=2026-11-01")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Ahmed')
        ->assertJsonPath('data.0.status', 'absent')
        ->assertJsonPath('data.1.name', 'Zeyad')
        ->assertJsonPath('data.1.status', null);

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson("/api/v1/training-groups/{$group->id}/attendance?date=2026-11-02")
        ->assertOk()
        ->assertJsonPath('data.0.status', null);
});

it('requires a valid date to read the sheet', function () {
    $tenant = TenantFixture::create();
    $group = attGroupFor($tenant);

    $this->withToken($tenant->token)
        ->getJson("/api/v1/training-groups/{$group->id}/attendance")
        ->assertStatus(422)
        ->assertJsonValidationErrors('date');
});

it('lets a coach read their own group sheet only, and still reads an inactive group', function () {
    $tenant = TenantFixture::create(RoleName::Coach);
    $own = attGroupFor($tenant, ['coach_id' => $tenant->user->id, 'is_active' => false]);
    $other = attGroupFor($tenant);

    $this->withToken($tenant->token)
        ->getJson("/api/v1/training-groups/{$own->id}/attendance?date=".attToday())
        ->assertOk();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson("/api/v1/training-groups/{$other->id}/attendance?date=".attToday())
        ->assertForbidden();
});

it('does not expose or change the attendance of another club group', function () {
    $a = TenantFixture::create();
    $b = TenantFixture::create();
    $group = attGroupFor($a);
    $player = attMember($a, $group);

    $this->withToken($b->token)
        ->getJson("/api/v1/training-groups/{$group->id}/attendance?date=".attToday())
        ->assertNotFound();

    $this->app['auth']->forgetGuards();

    $this->withToken($b->token)
        ->postJson("/api/v1/training-groups/{$group->id}/attendance", attPayload(attToday(), [
            ['player_id' => $player->id, 'status' => 'present'],
        ]))
        ->assertNotFound();

    expect(TrainingAttendance::query()->withoutGlobalScopes()->count())->toBe(0);
});
