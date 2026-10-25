<?php

use App\Domain\Auth\Actions\SyncClubRolesAction;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeClubUser(Club $club, RoleName $role): User
{
    app(SyncClubRolesAction::class)->execute($club);

    $user = User::factory()->forClub($club)->create();

    setPermissionsTeamId($club->id);
    $user->assignRole($role->value);

    return $user->fresh();
}

it('lets every role view its own club', function (RoleName $role) {
    $club = Club::factory()->create();
    $user = makeClubUser($club, $role);

    expect($user->can('view', $club))->toBeTrue();
})->with(RoleName::cases());

it('lets only the owner update the club', function (RoleName $role, bool $allowed) {
    $club = Club::factory()->create();
    $user = makeClubUser($club, $role);

    expect($user->can('update', $club))->toBe($allowed);
})->with([
    'owner' => [RoleName::Owner, true],
    'coach' => [RoleName::Coach, false],
    'staff' => [RoleName::Staff, false],
    'referee' => [RoleName::Referee, false],
]);

it('denies access to another club even for an owner', function () {
    $clubA = Club::factory()->create();
    $clubB = Club::factory()->create();
    $ownerA = makeClubUser($clubA, RoleName::Owner);

    setPermissionsTeamId($clubA->id);

    expect($ownerA->can('view', $clubB))->toBeFalse()
        ->and($ownerA->can('update', $clubB))->toBeFalse();
});

it('denies a user with no role', function () {
    $club = Club::factory()->create();
    app(SyncClubRolesAction::class)->execute($club);
    $user = User::factory()->forClub($club)->create();

    setPermissionsTeamId($club->id);

    expect($user->can('view', $club))->toBeFalse();
});
