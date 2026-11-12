<?php

use App\Domain\Auth\Actions\SyncClubRolesAction;
use App\Domain\Club\Models\Club;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('clears the cached permissions whenever the roles of a club are synced', function () {
    $club = Club::factory()->create();
    $key = config('permission.cache.key');

    app(SyncClubRolesAction::class)->execute($club);

    // Warm the cache the way a request would.
    app(PermissionRegistrar::class)->getPermissions();
    expect(Cache::has($key))->toBeTrue();

    app(SyncClubRolesAction::class)->execute($club);

    expect(Cache::has($key))->toBeFalse();
});
