<?php

namespace Tests\Support\Tenancy;

use App\Domain\Auth\Enums\PermissionName;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Models\Club;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * A club with one user (holding a role) and a Sanctum token.
 * Create two of them to test that clubs cannot see each other.
 *
 * The club roles are inserted in bulk instead of calling
 * SyncClubRolesAction, which flushes the spatie permission cache once
 * per role (and once per permission it creates). Each flush forces the
 * next permission lookup to rebuild the whole cache, so a fixture that
 * runs the action eight times per test costs seconds. The rows written
 * here are exactly the rows the action would write.
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

        self::syncClubRoles($club);

        $user = User::factory()->forClub($club)->create();

        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId($club->id);

        try {
            $user->assignRole($role->value);
        } finally {
            setPermissionsTeamId($previousTeamId);
        }

        return new self($club, $user, $user->createToken('test', ['*'])->plainTextToken);
    }

    /**
     * Mirror of SyncClubRolesAction, using bulk inserts and a single cache flush.
     *
     * Exposed so tests that only need the roles in place (policy and tenancy
     * tests) can avoid the action's repeated permission cache flushes.
     */
    public static function syncClubRoles(Club $club): void
    {
        $guard = config('auth.defaults.guard');
        $now = now();

        $permissionNames = array_map(
            fn (PermissionName $permission): string => $permission->value,
            PermissionName::cases(),
        );

        DB::table('permissions')->insertOrIgnore(
            array_map(fn (string $name): array => [
                'name' => $name,
                'guard_name' => $guard,
                'created_at' => $now,
                'updated_at' => $now,
            ], $permissionNames),
        );

        $permissionIds = Permission::query()
            ->whereIn('name', $permissionNames)
            ->where('guard_name', $guard)
            ->pluck('id', 'name');

        $roleIds = [];

        foreach (RoleName::cases() as $roleName) {
            $roleIds[$roleName->value] = Role::query()->insertGetId([
                'name' => $roleName->value,
                'guard_name' => $guard,
                'club_id' => $club->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $rows = [];

        foreach (RoleName::cases() as $roleName) {
            foreach ($roleName->permissions() as $permission) {
                $rows[] = [
                    'role_id' => $roleIds[$roleName->value],
                    'permission_id' => $permissionIds[$permission->value],
                ];
            }
        }

        DB::table('role_has_permissions')->insert($rows);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
