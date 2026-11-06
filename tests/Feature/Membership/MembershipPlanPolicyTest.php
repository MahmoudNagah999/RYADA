<?php

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Membership\Models\MembershipPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

function planPolicyCan(RoleName $role, string $ability, bool $ownPlan = true): bool
{
    $tenant = TenantFixture::create($role);
    $other = TenantFixture::create();
    $plan = MembershipPlan::factory()->create([
        'club_id' => ($ownPlan ? $tenant : $other)->club->id,
    ]);

    setPermissionsTeamId($tenant->club->id);

    return in_array($ability, ['viewAny', 'create'], true)
        ? Gate::forUser($tenant->user)->allows($ability, MembershipPlan::class)
        : Gate::forUser($tenant->user)->allows($ability, $plan);
}

it('lets the owner and staff view and manage membership plans', function (RoleName $role) {
    expect(planPolicyCan($role, 'viewAny'))->toBeTrue()
        ->and(planPolicyCan($role, 'view'))->toBeTrue()
        ->and(planPolicyCan($role, 'create'))->toBeTrue()
        ->and(planPolicyCan($role, 'update'))->toBeTrue();
})->with([RoleName::Owner, RoleName::Staff]);

it('lets a coach view but not manage membership plans', function () {
    expect(planPolicyCan(RoleName::Coach, 'viewAny'))->toBeTrue()
        ->and(planPolicyCan(RoleName::Coach, 'view'))->toBeTrue()
        ->and(planPolicyCan(RoleName::Coach, 'create'))->toBeFalse()
        ->and(planPolicyCan(RoleName::Coach, 'update'))->toBeFalse();
});

it('gives a referee no access to membership plans', function () {
    expect(planPolicyCan(RoleName::Referee, 'viewAny'))->toBeFalse()
        ->and(planPolicyCan(RoleName::Referee, 'view'))->toBeFalse();
});

it('denies access to a plan of another club even for the owner', function () {
    expect(planPolicyCan(RoleName::Owner, 'view', ownPlan: false))->toBeFalse()
        ->and(planPolicyCan(RoleName::Owner, 'update', ownPlan: false))->toBeFalse();
});
