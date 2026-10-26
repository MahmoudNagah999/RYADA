<?php

use App\Domain\Club\Models\Club;
use App\Domain\Subscription\Models\Plan;
use App\Filament\Resources\Clubs\Pages\ListClubs;
use App\Models\Admin;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    Filament::setCurrentPanel('admin');
});

it('assigns a plan to a club from the table', function () {
    $club = Club::factory()->create();
    $plan = Plan::factory()->create(['duration_months' => 6]);

    Livewire::test(ListClubs::class)
        ->callAction(TestAction::make('assignPlan')->table($club), [
            'plan_id' => $plan->id,
            'starts_at' => '2026-03-01',
        ])
        ->assertHasNoActionErrors();

    $club->refresh();

    expect($club->plan_id)->toBe($plan->id)
        ->and($club->subscription_starts_at->toDateString())->toBe('2026-03-01')
        ->and($club->subscription_ends_at->toDateString())->toBe('2026-09-01');
});

it('does not offer inactive plans', function () {
    $club = Club::factory()->create();
    $inactive = Plan::factory()->create(['is_active' => false]);

    Livewire::test(ListClubs::class)
        ->callAction(TestAction::make('assignPlan')->table($club), [
            'plan_id' => $inactive->id,
            'starts_at' => '2026-03-01',
        ])
        ->assertHasActionErrors(['plan_id']);

    expect($club->refresh()->plan_id)->toBeNull();
});
