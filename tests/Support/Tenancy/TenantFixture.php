<?php

namespace Tests\Support\Tenancy;

use App\Domain\Auth\Actions\SyncClubRolesAction;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Models\Club;
use App\Models\User;

/**
 * A club with one user (holding a role) and a Sanctum token.
 * Create two of them to test that clubs cannot see each other.
 */
final class TenantFixture
{
    private function __construct(
        public readonly Club $club,
        public readonly User $user,
        public readonly string $token,
    ) {}

    public static function create(RoleName $role = RoleName::Owner): self
    {
        $club = Club::factory()->create();
        app(SyncClubRolesAction::class)->execute($club);

        $user = User::factory()->forClub($club)->create();

        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId($club->id);
        $user->assignRole($role->value);
        setPermissionsTeamId($previousTeamId);

        return new self($club, $user, $user->createToken('test', ['*'])->plainTextToken);
    }
}
