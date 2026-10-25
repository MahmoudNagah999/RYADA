<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Enums\PermissionName;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Models\Club;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SyncClubRolesAction
{
    public function execute(Club $club): void
    {
        $guard = config('auth.defaults.guard');

        DB::transaction(function () use ($club, $guard): void {
            foreach (PermissionName::cases() as $permission) {
                Permission::findOrCreate($permission->value, $guard);
            }

            foreach (RoleName::cases() as $roleName) {
                $role = Role::query()->firstOrCreate([
                    'name' => $roleName->value,
                    'guard_name' => $guard,
                    'club_id' => $club->id,
                ]);

                $role->syncPermissions(
                    array_map(fn (PermissionName $p): string => $p->value, $roleName->permissions())
                );
            }
        });
    }
}
