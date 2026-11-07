<?php

use App\Domain\Auth\Actions\SyncClubRolesAction;
use App\Domain\Club\Models\Club;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $action = app(SyncClubRolesAction::class);

        Club::query()->each(fn (Club $club) => $action->execute($club));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Permissions are defined in code; nothing to roll back.
    }
};
