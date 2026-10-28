<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Club\Actions\SuspendClubAction;
use App\Domain\Club\Models\Club;
use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Models\Admin;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(Admin::factory()->create(), 'admin');
});

it('lists activities of every club for the super-admin', function () {
    $clubA = Club::factory()->create();
    $clubB = Club::factory()->create();

    app(SuspendClubAction::class)->execute($clubA);
    app(SuspendClubAction::class)->execute($clubB);

    $activities = Activity::query()->where('description', 'club.suspended')->get();

    Livewire::test(ListActivities::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($activities);
});

it('filters activities by club', function () {
    $clubA = Club::factory()->create();
    $clubB = Club::factory()->create();

    app(SuspendClubAction::class)->execute($clubA);
    app(SuspendClubAction::class)->execute($clubB);

    $forA = Activity::query()->where('club_id', $clubA->id)->get();
    $forB = Activity::query()->where('club_id', $clubB->id)->get();

    Livewire::test(ListActivities::class)
        ->filterTable('club_id', $clubA->id)
        ->assertCanSeeTableRecords($forA)
        ->assertCanNotSeeTableRecords($forB);
});

it('is read-only', function () {
    $club = Club::factory()->create();
    app(SuspendClubAction::class)->execute($club);

    $activity = Activity::query()->sole();

    expect(ActivityResource::canCreate())->toBeFalse()
        ->and(ActivityResource::canEdit($activity))->toBeFalse()
        ->and(ActivityResource::canDelete($activity))->toBeFalse()
        ->and(ActivityResource::canDeleteAny())->toBeFalse();
});
