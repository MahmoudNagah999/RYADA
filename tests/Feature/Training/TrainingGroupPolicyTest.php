<?php

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Training\Models\TrainingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

function groupPolicyCan(RoleName $role, string $ability, bool $ownGroup = true): bool
{
    $tenant = TenantFixture::create($role);
    $other = TenantFixture::create();
    $group = TrainingGroup::factory()->create([
        'club_id' => ($ownGroup ? $tenant : $other)->club->id,
    ]);

    setPermissionsTeamId($tenant->club->id);

    return in_array($ability, ['viewAny', 'create'], true)
        ? Gate::forUser($tenant->user)->allows($ability, TrainingGroup::class)
        : Gate::forUser($tenant->user)->allows($ability, $group);
}

it('lets the owner and staff view and manage training groups', function (RoleName $role) {
    expect(groupPolicyCan($role, 'viewAny'))->toBeTrue()
        ->and(groupPolicyCan($role, 'view'))->toBeTrue()
        ->and(groupPolicyCan($role, 'create'))->toBeTrue()
        ->and(groupPolicyCan($role, 'update'))->toBeTrue();
})->with([RoleName::Owner, RoleName::Staff]);

it('lets a coach view but not manage training groups', function () {
    expect(groupPolicyCan(RoleName::Coach, 'viewAny'))->toBeTrue()
        ->and(groupPolicyCan(RoleName::Coach, 'view'))->toBeTrue()
        ->and(groupPolicyCan(RoleName::Coach, 'create'))->toBeFalse()
        ->and(groupPolicyCan(RoleName::Coach, 'update'))->toBeFalse();
});

it('gives a referee no access to training groups', function () {
    expect(groupPolicyCan(RoleName::Referee, 'viewAny'))->toBeFalse()
        ->and(groupPolicyCan(RoleName::Referee, 'view'))->toBeFalse();
});

it('denies access to a group of another club even for the owner', function () {
    expect(groupPolicyCan(RoleName::Owner, 'view', ownGroup: false))->toBeFalse()
        ->and(groupPolicyCan(RoleName::Owner, 'update', ownGroup: false))->toBeFalse();
});
