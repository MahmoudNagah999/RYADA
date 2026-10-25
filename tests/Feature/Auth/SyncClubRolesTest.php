<?php

use App\Domain\Auth\Actions\SyncClubRolesAction;
use App\Domain\Auth\Enums\PermissionName;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Models\Club;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('creates every role for the club', function () {
    $club = Club::factory()->create();

    app(SyncClubRolesAction::class)->execute($club);

    expect(Role::where('club_id', $club->id)->pluck('name')->all())
        ->toEqualCanonicalizing(array_column(RoleName::cases(), 'value'));
});

it('is idempotent', function () {
    $club = Club::factory()->create();
    $action = app(SyncClubRolesAction::class);

    $action->execute($club);
    $action->execute($club);

    expect(Role::where('club_id', $club->id)->count())->toBe(count(RoleName::cases()))
        ->and(Permission::count())->toBe(count(PermissionName::cases()));
});

it('keeps roles separate per club', function () {
    $a = Club::factory()->create();
    $b = Club::factory()->create();
    $action = app(SyncClubRolesAction::class);

    $action->execute($a);
    $action->execute($b);

    expect(Role::count())->toBe(count(RoleName::cases()) * 2)
        ->and(Role::where('club_id', $a->id)->count())->toBe(count(RoleName::cases()))
        ->and(Role::where('club_id', $b->id)->count())->toBe(count(RoleName::cases()));
});

it('gives the owner every permission', function () {
    $club = Club::factory()->create();
    app(SyncClubRolesAction::class)->execute($club);

    $owner = Role::where('club_id', $club->id)->where('name', RoleName::Owner->value)->firstOrFail();

    expect($owner->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(array_column(PermissionName::cases(), 'value'));
});

it('limits the referee to scoring and read access', function () {
    $club = Club::factory()->create();
    app(SyncClubRolesAction::class)->execute($club);

    $referee = Role::where('club_id', $club->id)->where('name', RoleName::Referee->value)->firstOrFail();

    expect($referee->hasPermissionTo(PermissionName::ScoresSubmit->value))->toBeTrue()
        ->and($referee->hasPermissionTo(PermissionName::PlayersManage->value))->toBeFalse();
});
