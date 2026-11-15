<?php

use App\Domain\Auth\Actions\SyncClubRolesAction;
use App\Domain\Club\Models\Club;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

/**
 * @return list<string>
 */
function fxRolePermissionMap(int $clubId): array
{
    return DB::table('roles')
        ->join('role_has_permissions', 'roles.id', '=', 'role_has_permissions.role_id')
        ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
        ->where('roles.club_id', $clubId)
        ->get(['roles.name as role', 'roles.guard_name as role_guard', 'permissions.name as permission', 'permissions.guard_name as permission_guard'])
        ->map(fn (object $row): string => "{$row->role_guard}|{$row->role}|{$row->permission_guard}|{$row->permission}")
        ->sort()
        ->values()
        ->all();
}

/**
 * @return list<string>
 */
function fxRoleNames(int $clubId): array
{
    return DB::table('roles')
        ->where('club_id', $clubId)
        ->orderBy('name')
        ->pluck('name')
        ->all();
}

it('writes the same roles and permissions as SyncClubRolesAction', function () {
    $viaAction = Club::factory()->create();
    $viaFixture = Club::factory()->create();

    app(SyncClubRolesAction::class)->execute($viaAction);
    TenantFixture::syncClubRoles($viaFixture);

    expect(fxRoleNames($viaFixture->id))->toEqual(fxRoleNames($viaAction->id))
        ->and(fxRolePermissionMap($viaFixture->id))->toEqual(fxRolePermissionMap($viaAction->id))
        ->and(fxRolePermissionMap($viaAction->id))->not->toBeEmpty();
});
