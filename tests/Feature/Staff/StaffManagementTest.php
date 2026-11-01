<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Enums\ClubStatus;
use App\Domain\Subscription\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

it('lists only this club staff with role filters, search, sorting, and pagination', function () {
    $owner = TenantFixture::create(RoleName::Owner);
    $otherClub = TenantFixture::create(RoleName::Owner);

    $first = User::factory()->forClub($owner->club)->create([
        'name' => 'Coach Alpha',
        'email' => 'alpha@staff.test',
    ]);
    $second = User::factory()->forClub($owner->club)->create([
        'name' => 'Coach Beta',
        'email' => 'beta@staff.test',
    ]);

    $previousTeamId = getPermissionsTeamId();
    setPermissionsTeamId($owner->club->id);
    $first->assignRole(RoleName::Coach->value);
    $second->assignRole(RoleName::Coach->value);
    setPermissionsTeamId($previousTeamId);

    $this->withToken($owner->token)
        ->getJson('/api/v1/staff?role=coach&search=Coach&sort=email&direction=desc&per_page=1')
        ->assertOk()
        ->assertJsonPath('data.0.id', $second->id)
        ->assertJsonPath('data.0.role', RoleName::Coach->value)
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonMissing(['id' => $otherClub->user->id]);
});

it('allows owners and staff to view staff', function (RoleName $role) {
    $fixture = TenantFixture::create($role);

    $this->withToken($fixture->token)
        ->getJson('/api/v1/staff')
        ->assertOk()
        ->assertJsonPath('data.0.id', $fixture->user->id);
})->with([
    'owner' => RoleName::Owner,
    'staff' => RoleName::Staff,
]);

it('returns 403 when a coach or referee views staff', function (RoleName $role) {
    $fixture = TenantFixture::create($role);

    $this->withToken($fixture->token)
        ->getJson('/api/v1/staff')
        ->assertForbidden()
        ->assertJsonPath('code', 'http.forbidden');
})->with([
    'coach' => RoleName::Coach,
    'referee' => RoleName::Referee,
]);

it('returns 404 when changing a user role from another club', function () {
    $owner = TenantFixture::create(RoleName::Owner);
    $otherClub = TenantFixture::create(RoleName::Owner);

    $this->withToken($owner->token)
        ->patchJson("/api/v1/staff/{$otherClub->user->id}/role", ['role' => RoleName::Staff->value])
        ->assertNotFound()
        ->assertJsonPath('code', 'http.not_found');
});

it('returns 404 when removing a user from another club', function () {
    $owner = TenantFixture::create(RoleName::Owner);
    $otherClub = TenantFixture::create(RoleName::Owner);

    $this->withToken($owner->token)
        ->deleteJson("/api/v1/staff/{$otherClub->user->id}")
        ->assertNotFound()
        ->assertJsonPath('code', 'http.not_found');
});

it('changes a staff role and records the change', function () {
    $owner = TenantFixture::create(RoleName::Owner);
    $member = User::factory()->forClub($owner->club)->create();

    $previousTeamId = getPermissionsTeamId();
    setPermissionsTeamId($owner->club->id);
    $member->assignRole(RoleName::Staff->value);
    setPermissionsTeamId($previousTeamId);

    $this->withToken($owner->token)
        ->patchJson("/api/v1/staff/{$member->id}/role", ['role' => RoleName::Referee->value])
        ->assertOk()
        ->assertJsonPath('data.role', RoleName::Referee->value);

    setPermissionsTeamId($owner->club->id);

    expect($member->fresh()->hasRole(RoleName::Referee->value))->toBeTrue()
        ->and(Activity::query()->where('description', 'staff.role_changed')->count())->toBe(1);
});

it('returns 403 when Staff, Coach, or Referee changes a staff role', function (RoleName $role) {
    $actor = TenantFixture::create($role);
    $member = User::factory()->forClub($actor->club)->create();

    $previousTeamId = getPermissionsTeamId();
    setPermissionsTeamId($actor->club->id);
    $member->assignRole(RoleName::Staff->value);
    setPermissionsTeamId($previousTeamId);

    $this->withToken($actor->token)
        ->patchJson("/api/v1/staff/{$member->id}/role", ['role' => RoleName::Referee->value])
        ->assertForbidden()
        ->assertJsonPath('code', 'http.forbidden');
})->with([
    'staff' => RoleName::Staff,
    'coach' => RoleName::Coach,
    'referee' => RoleName::Referee,
]);

it('returns 403 when Staff, Coach, or Referee removes a staff member', function (RoleName $role) {
    $actor = TenantFixture::create($role);
    $member = User::factory()->forClub($actor->club)->create();

    $previousTeamId = getPermissionsTeamId();
    setPermissionsTeamId($actor->club->id);
    $member->assignRole(RoleName::Staff->value);
    setPermissionsTeamId($previousTeamId);

    $this->withToken($actor->token)
        ->deleteJson("/api/v1/staff/{$member->id}")
        ->assertForbidden()
        ->assertJsonPath('code', 'http.forbidden');

    expect($member->fresh())->not->toBeNull();
})->with([
    'staff' => RoleName::Staff,
    'coach' => RoleName::Coach,
    'referee' => RoleName::Referee,
]);

it('enforces the coach limit when a staff member is promoted to coach', function () {
    $owner = TenantFixture::create(RoleName::Owner);
    $owner->club->plan()->associate(Plan::factory()->create(['max_coaches' => 0]));
    $owner->club->save();
    $member = User::factory()->forClub($owner->club)->create();

    $previousTeamId = getPermissionsTeamId();
    setPermissionsTeamId($owner->club->id);
    $member->assignRole(RoleName::Staff->value);
    setPermissionsTeamId($previousTeamId);

    $this->withToken($owner->token)
        ->patchJson("/api/v1/staff/{$member->id}/role", ['role' => RoleName::Coach->value])
        ->assertForbidden()
        ->assertJsonPath('code', 'plan.coaches_limit_reached');

    setPermissionsTeamId($owner->club->id);

    expect($member->fresh()->hasRole(RoleName::Staff->value))->toBeTrue();
});

it('does not allow removing the current user', function () {
    $owner = TenantFixture::create(RoleName::Owner);

    $this->withToken($owner->token)
        ->deleteJson("/api/v1/staff/{$owner->user->id}")
        ->assertUnprocessable()
        ->assertJsonPath('code', 'staff.cannot_remove_self');

    expect($owner->user->fresh())->not->toBeNull();
});

it('removes a staff member and revokes their Sanctum tokens', function () {
    $owner = TenantFixture::create(RoleName::Owner);
    $member = User::factory()->forClub($owner->club)->create();

    $previousTeamId = getPermissionsTeamId();
    setPermissionsTeamId($owner->club->id);
    $member->assignRole(RoleName::Staff->value);
    $memberToken = $member->createToken('staff-test')->accessToken;
    setPermissionsTeamId($previousTeamId);

    $this->withToken($owner->token)
        ->deleteJson("/api/v1/staff/{$member->id}")
        ->assertNoContent();

    expect(User::query()->find($member->id))->toBeNull()
        ->and($memberToken->fresh())->toBeNull()
        ->and(Activity::query()->where('description', 'staff.removed')->count())->toBe(1);
});

it('enforces suspension and expiry on staff routes', function (string $condition, int $status, string $code) {
    $owner = TenantFixture::create(RoleName::Owner);

    if ($condition === 'suspended') {
        $owner->club->status = ClubStatus::Suspended;
        $owner->club->suspended_at = now();
    } else {
        $owner->club->subscription_ends_at = now()->subDay();
    }

    $owner->club->save();

    $this->withToken($owner->token)
        ->getJson('/api/v1/staff')
        ->assertStatus($status)
        ->assertJsonPath('code', $code);
})->with([
    'suspended club' => ['suspended', 403, 'club.suspended'],
    'expired subscription' => ['expired', 402, 'subscription.expired'],
]);
