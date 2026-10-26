<?php

use App\Domain\Club\Models\Club;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Subscription\Actions\AssignPlanToClubAction;
use App\Domain\Subscription\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('attaches the plan and computes the subscription dates', function () {
    $this->travelTo('2026-01-15 10:00:00');

    $club = Club::factory()->create();
    $plan = Plan::factory()->create(['duration_months' => 12]);

    app(AssignPlanToClubAction::class)->execute($club, $plan);

    $club->refresh();

    expect($club->plan_id)->toBe($plan->id)
        ->and($club->subscription_starts_at->toDateString())->toBe('2026-01-15')
        ->and($club->subscription_ends_at->toDateString())->toBe('2027-01-15');
});

it('does not overflow into the next month at the end of a month', function () {
    $club = Club::factory()->create();
    $plan = Plan::factory()->create(['duration_months' => 1]);

    app(AssignPlanToClubAction::class)->execute($club, $plan, now()->setDate(2026, 1, 31));

    expect($club->refresh()->subscription_ends_at->toDateString())->toBe('2026-02-28');
});

it('rejects an inactive plan and leaves the club untouched', function () {
    $club = Club::factory()->create();
    $plan = Plan::factory()->create(['is_active' => false]);

    expect(fn () => app(AssignPlanToClubAction::class)->execute($club, $plan))
        ->toThrow(BusinessRuleException::class);

    $club->refresh();

    expect($club->plan_id)->toBeNull()
        ->and($club->subscription_ends_at)->toBeNull();
});
