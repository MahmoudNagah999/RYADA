<?php

use App\Domain\Auth\Actions\SyncClubRolesAction;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Models\Club;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(RefreshDatabase::class);

const TENANT_PROBE_URL = '/api/v1/_tenant-probe';

beforeEach(function () {
    Route::middleware(['api', 'auth:sanctum', 'tenant'])
        ->get(TENANT_PROBE_URL, fn (Request $request) => response()->json([
            'club_id' => app(CurrentClub::class)->id(),
            'team_id' => getPermissionsTeamId(),
            'roles' => $request->user()->getRoleNames()->all(),
        ]));
});

function tenantProbeUser(Club $club, RoleName $role): User
{
    app(SyncClubRolesAction::class)->execute($club);

    $user = User::factory()->forClub($club)->create();

    setPermissionsTeamId($club->id);
    $user->assignRole($role->value);
    setPermissionsTeamId(null);

    return $user;
}

it('sets the current club and the permissions team from the authenticated user', function () {
    /** @var TestCase $this */
    $club = Club::factory()->create();
    Sanctum::actingAs(tenantProbeUser($club, RoleName::Coach));

    $this->getJson(TENANT_PROBE_URL)
        ->assertOk()
        ->assertJsonPath('club_id', $club->id)
        ->assertJsonPath('team_id', $club->id)
        ->assertJsonPath('roles', [RoleName::Coach->value]);
});

it('resolves the club per request without leaking between users', function () {
    /** @var TestCase $this */
    $clubA = Club::factory()->create();
    $clubB = Club::factory()->create();
    $tokenA = tenantProbeUser($clubA, RoleName::Owner)->createToken('t')->plainTextToken;
    $tokenB = tenantProbeUser($clubB, RoleName::Referee)->createToken('t')->plainTextToken;

    $this->withToken($tokenA)->getJson(TENANT_PROBE_URL)
        ->assertOk()
        ->assertJsonPath('club_id', $clubA->id)
        ->assertJsonPath('roles', [RoleName::Owner->value]);

    $this->app['auth']->forgetGuards();

    $this->withToken($tokenB)->getJson(TENANT_PROBE_URL)
        ->assertOk()
        ->assertJsonPath('club_id', $clubB->id)
        ->assertJsonPath('roles', [RoleName::Referee->value]);
});

it('restores the previous context after the request', function () {
    /** @var TestCase $this */
    $club = Club::factory()->create();
    Sanctum::actingAs(tenantProbeUser($club, RoleName::Staff));

    $this->getJson(TENANT_PROBE_URL)->assertOk();

    expect(app(CurrentClub::class)->has())->toBeFalse()
        ->and(getPermissionsTeamId())->toBeNull();
});

it('rejects a user that has no club', function () {
    /** @var TestCase $this */
    // users.club_id is required, so this only simulates a dangling reference.
    Sanctum::actingAs(User::factory()->make(['club_id' => 999_999]));

    $response = $this->getJson(TENANT_PROBE_URL)
        ->assertForbidden()
        ->assertJsonPath('code', 'tenant.no_club');

    expect($response->json('message'))->not->toBe('errors.tenant.no_club')
        ->and(app(CurrentClub::class)->has())->toBeFalse();
});
