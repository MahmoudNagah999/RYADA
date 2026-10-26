<?php

use App\Domain\Club\Actions\ActivateClubAction;
use App\Domain\Club\Actions\SuspendClubAction;
use App\Domain\Club\Enums\ClubStatus;
use App\Domain\Club\Models\Club;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates clubs as active', function () {
    $club = Club::factory()->create()->refresh();

    expect($club->status)->toBe(ClubStatus::Active)
        ->and($club->suspended_at)->toBeNull();
});

it('suspends an active club and records when', function () {
    $club = Club::factory()->create();

    app(SuspendClubAction::class)->execute($club);

    $club->refresh();

    expect($club->isSuspended())->toBeTrue()
        ->and($club->suspended_at)->not->toBeNull();
});

it('keeps the original suspension date when suspending twice', function () {
    $club = Club::factory()->create();
    $action = app(SuspendClubAction::class);

    $this->travelTo(now()->subDay());
    $action->execute($club);
    $first = $club->refresh()->suspended_at;

    $this->travelBack();
    $action->execute($club);

    expect($club->refresh()->suspended_at->equalTo($first))->toBeTrue();
});

it('reactivates a suspended club and clears the suspension date', function () {
    $club = Club::factory()->create();
    app(SuspendClubAction::class)->execute($club);

    app(ActivateClubAction::class)->execute($club);

    $club->refresh();

    expect($club->isActive())->toBeTrue()
        ->and($club->suspended_at)->toBeNull();
});
