<?php

use App\Domain\Auth\Enums\PermissionName;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\Tenancy\TenantFixture;
use Tests\TestCase;

uses(RefreshDatabase::class);

const ISOLATION_ME_URL = '/api/v1/auth/me';

/** Sends a GET with the given token, resetting the cached guard user between requests. */
function tenantGet(TestCase $test, string $token, string $url, array $headers = [])
{
    app('auth')->forgetGuards();

    return $test->withToken($token)->getJson($url, $headers);
}

it('shows each user only their own user and club', function () {
    /** @var TestCase $this */
    $a = TenantFixture::create(RoleName::Owner);
    $b = TenantFixture::create(RoleName::Coach);

    tenantGet($this, $a->token, ISOLATION_ME_URL)
        ->assertOk()
        ->assertJsonPath('data.email', $a->user->email)
        ->assertJsonPath('data.club.slug', $a->club->slug);

    tenantGet($this, $b->token, ISOLATION_ME_URL)
        ->assertOk()
        ->assertJsonPath('data.email', $b->user->email)
        ->assertJsonPath('data.club.slug', $b->club->slug);
});

it('cannot be pointed at another club through the request', function () {
    /** @var TestCase $this */
    $a = TenantFixture::create();
    $b = TenantFixture::create();

    $attempts = [
        [ISOLATION_ME_URL.'?club_id='.$b->club->id, []],
        [ISOLATION_ME_URL.'?club='.$b->club->slug, []],
        [ISOLATION_ME_URL, ['X-Club-Id' => (string) $b->club->id]],
        [ISOLATION_ME_URL, ['X-Club' => $b->club->slug]],
    ];

    foreach ($attempts as [$url, $headers]) {
        tenantGet($this, $a->token, $url, $headers)
            ->assertOk()
            ->assertJsonPath('data.email', $a->user->email)
            ->assertJsonPath('data.club.slug', $a->club->slug);
    }
});

it('leaves existing clubs untouched when another club registers', function () {
    /** @var TestCase $this */
    $a = TenantFixture::create();
    $rolesBefore = Role::where('club_id', $a->club->id)->count();
    $usersBefore = User::where('club_id', $a->club->id)->count();

    $this->postJson('/api/v1/auth/register-club', [
        'club_name' => 'Another Club',
        'name' => 'Someone Else',
        'email' => 'someone@example.com',
        'password' => 'password123',
    ])->assertCreated();

    expect(Club::count())->toBe(2)
        ->and(Role::where('club_id', $a->club->id)->count())->toBe($rolesBefore)
        ->and(User::where('club_id', $a->club->id)->count())->toBe($usersBefore)
        ->and($a->club->fresh()->name)->toBe($a->club->name);
});

it('keeps permission checks per club when the team switches', function () {
    $owner = TenantFixture::create(RoleName::Owner);
    $referee = TenantFixture::create(RoleName::Referee);

    setPermissionsTeamId($owner->club->id);
    expect($owner->user->fresh()->can('update', $owner->club))->toBeTrue()
        ->and($owner->user->fresh()->hasPermissionTo(PermissionName::PlayersManage->value))->toBeTrue();

    setPermissionsTeamId($referee->club->id);
    expect($referee->user->fresh()->can('update', $referee->club))->toBeFalse()
        ->and($referee->user->fresh()->hasPermissionTo(PermissionName::PlayersManage->value))->toBeFalse()
        // The owner holds no role at all inside the other club.
        ->and($owner->user->fresh()->hasPermissionTo(PermissionName::PlayersManage->value))->toBeFalse()
        ->and($owner->user->fresh()->can('view', $referee->club))->toBeFalse();

    // Switching back must not be poisoned by the previous team's cached state.
    setPermissionsTeamId($owner->club->id);
    expect($owner->user->fresh()->can('update', $owner->club))->toBeTrue();
});
