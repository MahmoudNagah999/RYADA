<?php

use App\Domain\Subscription\Models\Plan;
use App\Filament\Resources\Plans\Pages\CreatePlan;
use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Filament\Resources\Plans\PlanResource;
use App\Models\Admin;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    Filament::setCurrentPanel('admin');
});

it('creates a plan with both names and stores the price in cents', function () {
    Livewire::test(CreatePlan::class)
        ->fillForm([
            'name' => ['en' => 'Pro', 'ar' => 'احترافي'],
            'slug' => 'pro',
            'price_cents' => 500,
            'duration_months' => 12,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $plan = Plan::query()->where('slug', 'pro')->firstOrFail();

    expect($plan->price_cents)->toBe(50000)
        ->and($plan->getTranslation('name', 'ar'))->toBe('احترافي')
        ->and($plan->max_players)->toBeNull()
        ->and($plan->max_coaches)->toBeNull();
});

it('rejects a duplicate slug', function () {
    Plan::factory()->create(['slug' => 'pro']);

    Livewire::test(CreatePlan::class)
        ->fillForm([
            'name' => ['en' => 'Pro 2', 'ar' => 'احترافي ٢'],
            'slug' => 'pro',
            'price_cents' => 100,
            'duration_months' => 1,
        ])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'unique']);
});

it('loads both translations when editing and saves changes', function () {
    $plan = Plan::factory()->create(['name' => ['en' => 'Basic', 'ar' => 'أساسي']]);

    Livewire::test(EditPlan::class, ['record' => $plan->getRouteKey()])
        ->assertFormSet(['name.en' => 'Basic', 'name.ar' => 'أساسي'])
        ->fillForm(['name' => ['en' => 'Starter', 'ar' => 'مبتدئ']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($plan->refresh()->getTranslation('name', 'en'))->toBe('Starter');
});

it('never allows deleting a plan from the panel', function () {
    $plan = Plan::factory()->create();

    expect(PlanResource::canDelete($plan))->toBeFalse()
        ->and(PlanResource::canDeleteAny())->toBeFalse();
});
