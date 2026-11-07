<?php

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Training\Models\TrainingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

function attendancePolicyCan(RoleName $role, string $ability, bool $ownClub = true, bool $isGroupCoach = false): bool
{
    $tenant = TenantFixture::create($role);
    $other = TenantFixture::create();
    $group = TrainingGroup::factory()->create([
        'club_id' => ($ownClub ? $tenant : $other)->club->id,
        'coach_id' => $isGroupCoach ? $tenant->user->id : null,
    ]);

    setPermissionsTeamId($tenant->club->id);

    return Gate::forUser($tenant->user)->allows($ability, $group);
}

it('lets the owner and staff handle the attendance of any group of the club', function (RoleName $role) {
    expect(attendancePolicyCan($role, 'viewAttendance'))->toBeTrue()
        ->and(attendancePolicyCan($role, 'recordAttendance'))->toBeTrue();
})->with([RoleName::Owner, RoleName::Staff]);

it('lets a coach handle the attendance of their own groups only', function () {
    expect(attendancePolicyCan(RoleName::Coach, 'viewAttendance', isGroupCoach: true))->toBeTrue()
        ->and(attendancePolicyCan(RoleName::Coach, 'recordAttendance', isGroupCoach: true))->toBeTrue()
        ->and(attendancePolicyCan(RoleName::Coach, 'viewAttendance'))->toBeFalse()
        ->and(attendancePolicyCan(RoleName::Coach, 'recordAttendance'))->toBeFalse();
});

it('gives a referee no access to attendance', function () {
    expect(attendancePolicyCan(RoleName::Referee, 'viewAttendance'))->toBeFalse()
        ->and(attendancePolicyCan(RoleName::Referee, 'recordAttendance', isGroupCoach: true))->toBeFalse();
});

it('denies attendance of a group of another club even for the owner', function () {
    expect(attendancePolicyCan(RoleName::Owner, 'viewAttendance', ownClub: false))->toBeFalse()
        ->and(attendancePolicyCan(RoleName::Owner, 'recordAttendance', ownClub: false))->toBeFalse();
});
