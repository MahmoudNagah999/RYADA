<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Club\Actions\ActivateClubAction;
use App\Domain\Club\Actions\CreateClubAction;
use App\Domain\Club\Actions\SuspendClubAction;
use App\Domain\Club\Models\Club;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Subscription\Actions\AssignPlanToClubAction;
use App\Domain\Subscription\Models\Plan;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records club.created with the new club id and no causer on public registration', function () {
    $club = app(CreateClubAction::class)->execute('Nile Karate Club');

    $activity = Activity::query()->where('description', 'club.created')->sole();

    expect($activity->club_id)->toBe($club->id)
        ->and($activity->subject_id)->toBe($club->id)
        ->and($activity->causer_id)->toBeNull()
        ->and($activity->properties->get('slug'))->toBe($club->slug);
});

it('records club.suspended once and skips the idempotent no-op', function () {
    $club = Club::factory()->create();

    app(SuspendClubAction::class)->execute($club);
    app(SuspendClubAction::class)->execute($club->refresh());

    $activity = Activity::query()->where('description', 'club.suspended')->sole();

    expect($activity->club_id)->toBe($club->id)
        ->and($activity->properties->get('status'))->toEqual(['from' => 'active', 'to' => 'suspended']);
});

it('records club.activated only when the club was suspended', function () {
    $club = Club::factory()->create();

    app(ActivateClubAction::class)->execute($club);

    expect(Activity::query()->where('description', 'club.activated')->exists())->toBeFalse();

    app(SuspendClubAction::class)->execute($club);
    app(ActivateClubAction::class)->execute($club->refresh());

    $activity = Activity::query()->where('description', 'club.activated')->sole();

    expect($activity->club_id)->toBe($club->id)
        ->and($activity->properties->get('status'))->toEqual(['from' => 'suspended', 'to' => 'active']);
});

it('records club.plan_assigned with the plan and the previous plan', function () {
    $first = Plan::factory()->create(['is_active' => true]);
    $second = Plan::factory()->create(['is_active' => true]);
    $club = Club::factory()->create();

    app(AssignPlanToClubAction::class)->execute($club, $first);
    app(AssignPlanToClubAction::class)->execute($club->refresh(), $second);

    $activity = Activity::query()
        ->where('description', 'club.plan_assigned')
        ->latest('id')
        ->first();

    expect($activity->club_id)->toBe($club->id)
        ->and($activity->properties->get('plan_id'))->toBe($second->id)
        ->and($activity->properties->get('previous_plan_id'))->toBe($first->id);
});

it('does not record anything when the plan is inactive', function () {
    $plan = Plan::factory()->create(['is_active' => false]);
    $club = Club::factory()->create();

    expect(fn () => app(AssignPlanToClubAction::class)->execute($club, $plan))
        ->toThrow(BusinessRuleException::class);

    expect(Activity::query()->count())->toBe(0);
});

it('uses the admin as causer when acting through the admin guard', function () {
    $admin = Admin::factory()->create();
    $club = Club::factory()->create();

    $this->actingAs($admin, 'admin');

    app(SuspendClubAction::class)->execute($club);

    $activity = Activity::query()->where('description', 'club.suspended')->sole();

    expect($activity->causer_type)->toBe($admin->getMorphClass())
        ->and($activity->causer_id)->toBe($admin->id);
});
