<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Domain\Staff\Actions\ChangeStaffRoleAction;
use App\Domain\Staff\Actions\InviteStaffAction;
use App\Domain\Staff\Actions\RemoveStaffAction;
use App\Domain\Staff\Actions\RevokeStaffInvitationAction;
use App\Domain\Staff\DTOs\ChangeStaffRoleData;
use App\Domain\Staff\DTOs\InviteStaffData;
use App\Domain\Staff\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

it('rejects removing the current user at the action layer', function () {
    $owner = TenantFixture::create(RoleName::Owner);

    expect(fn () => app(RemoveStaffAction::class)->execute($owner->user, $owner->user))
        ->toThrow(BusinessRuleException::class, 'staff.cannot_remove_self');
});

it('protects the only Owner from removal', function () {
    $owner = TenantFixture::create(RoleName::Owner);
    $secondOwner = User::factory()->forClub($owner->club)->create();
    $staff = User::factory()->forClub($owner->club)->create();

    $previousTeamId = getPermissionsTeamId();
    setPermissionsTeamId($owner->club->id);
    $secondOwner->assignRole(RoleName::Owner->value);
    $staff->assignRole(RoleName::Staff->value);
    setPermissionsTeamId($previousTeamId);

    expect(fn () => app(RemoveStaffAction::class)->execute($secondOwner, $owner->user))
        ->toThrow(BusinessRuleException::class, 'staff.cannot_remove_owner');

    expect($owner->user->fresh())->not->toBeNull();
});

it('does not allow changing an Owners role in the action', function () {
    $owner = TenantFixture::create(RoleName::Owner);
    $secondOwner = User::factory()->forClub($owner->club)->create();

    $previousTeamId = getPermissionsTeamId();
    setPermissionsTeamId($owner->club->id);
    $secondOwner->assignRole(RoleName::Owner->value);
    setPermissionsTeamId($previousTeamId);

    expect(fn () => app(ChangeStaffRoleAction::class)->execute(
        $owner->user,
        $secondOwner,
        new ChangeStaffRoleData(RoleName::Staff),
    ))->toThrow(BusinessRuleException::class, 'staff.cannot_change_owner_role');

    setPermissionsTeamId($owner->club->id);

    expect($secondOwner->fresh()->hasRole(RoleName::Owner->value))->toBeTrue();
});

it('does not allow assigning the Owner role through invitation actions', function () {
    Mail::fake();
    $owner = TenantFixture::create(RoleName::Owner);

    expect(fn () => app(InviteStaffAction::class)->execute(
        $owner->club,
        $owner->user,
        InviteStaffData::fromArray([
            'email' => 'new-owner@example.test',
            'role' => RoleName::Owner->value,
            'locale' => 'en',
        ]),
    ))->toThrow(BusinessRuleException::class, 'staff.owner_role_not_assignable');

    expect(StaffInvitation::query()->count())->toBe(0);
});

it('does not log role changes that are idempotent', function () {
    $owner = TenantFixture::create(RoleName::Owner);
    $staff = User::factory()->forClub($owner->club)->create();

    $previousTeamId = getPermissionsTeamId();
    setPermissionsTeamId($owner->club->id);
    $staff->assignRole(RoleName::Staff->value);
    setPermissionsTeamId($previousTeamId);

    app(ChangeStaffRoleAction::class)->execute(
        $owner->user,
        $staff,
        new ChangeStaffRoleData(RoleName::Staff),
    );

    expect(Activity::query()->where('description', 'staff.role_changed')->exists())->toBeFalse();
});

it('does not log a repeated invitation revocation', function () {
    $owner = TenantFixture::create(RoleName::Owner);
    $invitation = StaffInvitation::factory()->for($owner->club)->create([
        'revoked_at' => now(),
    ]);

    app(CurrentClub::class)->runAs($owner->club, function () use ($owner, $invitation): void {
        app(RevokeStaffInvitationAction::class)->execute($owner->user, $invitation);
    });

    expect(Activity::query()->where('description', 'staff.invitation_revoked')->exists())->toBeFalse();
});
