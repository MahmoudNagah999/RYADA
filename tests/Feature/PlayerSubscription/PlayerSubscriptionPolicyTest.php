<?php

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Player\Models\Player;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

function subPolicyCan(RoleName $role, string $ability, bool $ownSubscription = true): bool
{
    $tenant = TenantFixture::create($role);
    $other = TenantFixture::create();

    $clubId = ($ownSubscription ? $tenant : $other)->club->id;
    $player = Player::factory()->create(['club_id' => $clubId]);
    $subscription = PlayerSubscription::factory()->create([
        'club_id' => $clubId,
        'player_id' => $player->id,
    ]);

    setPermissionsTeamId($tenant->club->id);

    return in_array($ability, ['viewAny', 'create'], true)
        ? Gate::forUser($tenant->user)->allows($ability, PlayerSubscription::class)
        : Gate::forUser($tenant->user)->allows($ability, $subscription);
}

it('lets the owner and staff view and manage player subscriptions', function (RoleName $role) {
    expect(subPolicyCan($role, 'viewAny'))->toBeTrue()
        ->and(subPolicyCan($role, 'view'))->toBeTrue()
        ->and(subPolicyCan($role, 'create'))->toBeTrue()
        ->and(subPolicyCan($role, 'renew'))->toBeTrue()
        ->and(subPolicyCan($role, 'cancel'))->toBeTrue();
})->with([RoleName::Owner, RoleName::Staff]);

it('lets a coach view but not manage player subscriptions', function () {
    expect(subPolicyCan(RoleName::Coach, 'viewAny'))->toBeTrue()
        ->and(subPolicyCan(RoleName::Coach, 'view'))->toBeTrue()
        ->and(subPolicyCan(RoleName::Coach, 'create'))->toBeFalse()
        ->and(subPolicyCan(RoleName::Coach, 'renew'))->toBeFalse()
        ->and(subPolicyCan(RoleName::Coach, 'cancel'))->toBeFalse();
});

it('gives a referee no access to player subscriptions', function () {
    expect(subPolicyCan(RoleName::Referee, 'viewAny'))->toBeFalse()
        ->and(subPolicyCan(RoleName::Referee, 'view'))->toBeFalse()
        ->and(subPolicyCan(RoleName::Referee, 'create'))->toBeFalse();
});

it('denies access to a subscription of another club even for the owner', function () {
    expect(subPolicyCan(RoleName::Owner, 'view', ownSubscription: false))->toBeFalse()
        ->and(subPolicyCan(RoleName::Owner, 'renew', ownSubscription: false))->toBeFalse()
        ->and(subPolicyCan(RoleName::Owner, 'cancel', ownSubscription: false))->toBeFalse();
});
