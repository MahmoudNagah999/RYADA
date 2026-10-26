<?php

use App\Domain\Club\Models\Club;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Subscription\Actions\EnsureWithinPlanLimitAction;
use App\Domain\Subscription\Enums\PlanLimit;
use App\Domain\Subscription\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function clubOnPlan(array $planAttributes = []): Club
{
    $club = Club::factory()->create();
    $club->plan()->associate(Plan::factory()->create($planAttributes));
    $club->save();

    return $club;
}

it('allows anything for a club without a plan', function () {
    $club = Club::factory()->create();

    app(EnsureWithinPlanLimitAction::class)->execute($club, PlanLimit::Players, 10_000);

    expect(true)->toBeTrue();
});

it('allows anything when the plan has no limit', function () {
    $club = clubOnPlan(['max_players' => null]);

    app(EnsureWithinPlanLimitAction::class)->execute($club, PlanLimit::Players, 10_000);

    expect(true)->toBeTrue();
});

it('allows adding up to exactly the limit', function () {
    $club = clubOnPlan(['max_players' => 50]);

    app(EnsureWithinPlanLimitAction::class)->execute($club, PlanLimit::Players, 49);

    expect(true)->toBeTrue();
});

it('rejects adding one more when the club is at the limit', function () {
    $club = clubOnPlan(['max_players' => 50]);

    expect(fn () => app(EnsureWithinPlanLimitAction::class)->execute($club, PlanLimit::Players, 50))
        ->toThrow(BusinessRuleException::class);
});

it('rejects a bulk add that would cross the limit', function () {
    $club = clubOnPlan(['max_players' => 50]);

    expect(fn () => app(EnsureWithinPlanLimitAction::class)->execute($club, PlanLimit::Players, 40, 11))
        ->toThrow(BusinessRuleException::class);
});

it('allows a bulk add that lands exactly on the limit', function () {
    $club = clubOnPlan(['max_players' => 50]);

    app(EnsureWithinPlanLimitAction::class)->execute($club, PlanLimit::Players, 40, 10);

    expect(true)->toBeTrue();
});

it('checks players and coaches limits independently', function () {
    $club = clubOnPlan(['max_players' => 50, 'max_coaches' => 2]);
    $action = app(EnsureWithinPlanLimitAction::class);

    $action->execute($club, PlanLimit::Players, 10);

    expect(fn () => $action->execute($club, PlanLimit::Coaches, 2))
        ->toThrow(BusinessRuleException::class);
});

it('maps each limit to its plan column and error code', function () {
    expect(PlanLimit::Players->column())->toBe('max_players')
        ->and(PlanLimit::Coaches->column())->toBe('max_coaches')
        ->and(trans('errors.'.PlanLimit::Players->errorCode(), ['max' => 50], 'en'))
        ->toContain('50')
        ->and(trans('errors.'.PlanLimit::Coaches->errorCode(), ['max' => 5], 'ar'))
        ->toContain('5');
});
