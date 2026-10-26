<?php

use App\Domain\Club\Models\Club;
use App\Filament\Resources\Clubs\ClubResource;
use App\Filament\Resources\Clubs\Pages\CreateClub;
use App\Filament\Resources\Clubs\Pages\ListClubs;
use App\Models\Admin;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    Filament::setCurrentPanel('admin');
});

it('lists clubs for the admin', function () {
    $clubs = Club::factory()->count(2)->create();

    Livewire::test(ListClubs::class)->assertCanSeeTableRecords($clubs);
});

it('creates a club with its roles from the admin panel', function () {
    Livewire::test(CreateClub::class)
        ->fillForm(['name' => 'Zeitoun Karate Club'])
        ->call('create')
        ->assertHasNoFormErrors();

    $club = Club::query()->where('slug', 'zeitoun-karate-club')->firstOrFail();

    expect($club->isActive())->toBeTrue()
        ->and(Role::query()->where('club_id', $club->id)->count())->toBe(4);
});

it('requires a club name', function () {
    Livewire::test(CreateClub::class)
        ->fillForm(['name' => ''])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

it('suspends and activates a club from the table', function () {
    $club = Club::factory()->create();

    Livewire::test(ListClubs::class)
        ->assertActionHidden(TestAction::make('activate')->table($club))
        ->callAction(TestAction::make('suspend')->table($club));

    expect($club->refresh()->isSuspended())->toBeTrue();

    Livewire::test(ListClubs::class)
        ->assertActionHidden(TestAction::make('suspend')->table($club))
        ->callAction(TestAction::make('activate')->table($club));

    expect($club->refresh()->isActive())->toBeTrue();
});

it('never allows deleting a club from the panel', function () {
    $club = Club::factory()->create();

    expect(ClubResource::canDelete($club))->toBeFalse()
        ->and(ClubResource::canDeleteAny())->toBeFalse();
});

it('lets admins through club policies without opening them to club users', function () {
    $club = Club::factory()->create();
    $admin = Admin::factory()->create();
    $user = User::factory()->forClub($club)->create();

    expect(Gate::forUser($admin)->allows('view', $club))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view', $club))->toBeFalse();
});
