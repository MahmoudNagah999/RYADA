<?php

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Staff\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

it('allows only Owners to manage staff while Staff can view', function (
    RoleName $role,
    bool $canView,
    bool $canManage,
) {
    $fixture = TenantFixture::create($role);
    $member = User::factory()->forClub($fixture->club)->create();
    $invitation = StaffInvitation::factory()->for($fixture->club)->create();

    setPermissionsTeamId($fixture->club->id);

    expect($fixture->user->can('viewAny', User::class))->toBe($canView)
        ->and($fixture->user->can('viewAny', StaffInvitation::class))->toBe($canView)
        ->and($fixture->user->can('view', $member))->toBe($canView)
        ->and($fixture->user->can('create', StaffInvitation::class))->toBe($canManage)
        ->and($fixture->user->can('manage', $member))->toBe($canManage)
        ->and($fixture->user->can('manage', $invitation))->toBe($canManage);
})->with([
    'owner' => [RoleName::Owner, true, true],
    'staff' => [RoleName::Staff, true, false],
    'coach' => [RoleName::Coach, false, false],
    'referee' => [RoleName::Referee, false, false],
]);
