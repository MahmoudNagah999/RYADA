<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Subscription\Actions\CreatePlanAction;
use App\Domain\Subscription\Actions\UpdatePlanAction;
use App\Domain\Subscription\DTOs\PlanData;
use App\Domain\Subscription\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records plan.created without a club', function () {
    $plan = app(CreatePlanAction::class)->execute(PlanData::fromArray([
        'slug' => 'starter',
        'name' => ['en' => 'Starter', 'ar' => 'المبتدئ'],
        'max_players' => 50,
        'max_coaches' => 3,
        'price_cents' => 100000,
        'duration_months' => 12,
        'is_active' => true,
    ]));

    $activity = Activity::query()->where('description', 'plan.created')->sole();

    expect($activity->club_id)->toBeNull()
        ->and($activity->subject_id)->toBe($plan->id)
        ->and($activity->properties->get('slug'))->toBe('starter')
        ->and($activity->properties->get('price_cents'))->toBe(100000);
});

it('records only the changed fields on plan.updated', function () {
    $plan = Plan::factory()->create(['max_players' => 50, 'price_cents' => 100000]);

    $data = PlanData::fromArray([
        ...PlanData::fromPlan($plan)->toAttributes(),
        'max_players' => 80,
    ]);

    app(UpdatePlanAction::class)->execute($plan, $data);

    $activity = Activity::query()->where('description', 'plan.updated')->sole();

    expect($activity->club_id)->toBeNull()
        ->and($activity->properties->get('changes'))->toEqual([
            'max_players' => ['from' => 50, 'to' => 80],
        ])
        ->and($plan->refresh()->max_players)->toBe(80);
});

it('logs translatable name changes as readable arrays', function () {
    $plan = Plan::factory()->create();
    $plan->setTranslations('name', ['en' => 'Old', 'ar' => 'قديم'])->save();
    $plan->refresh();

    $data = PlanData::fromArray([
        ...PlanData::fromPlan($plan)->toAttributes(),
        'name' => ['en' => 'New', 'ar' => 'قديم'],
    ]);

    app(UpdatePlanAction::class)->execute($plan, $data);

    $activity = Activity::query()->where('description', 'plan.updated')->sole();

    expect($activity->properties->get('changes'))->toEqual([
        'name' => [
            'from' => ['en' => 'Old', 'ar' => 'قديم'],
            'to' => ['en' => 'New', 'ar' => 'قديم'],
        ],
    ]);
});

it('does not record anything when nothing changed', function () {
    $plan = Plan::factory()->create(['max_players' => 50]);

    app(UpdatePlanAction::class)->execute($plan, PlanData::fromPlan($plan));

    expect(Activity::query()->where('description', 'plan.updated')->exists())->toBeFalse();
});

it('treats an empty limit as unlimited (null)', function () {
    $data = PlanData::fromArray([
        'slug' => 'pro',
        'name' => ['en' => 'Pro', 'ar' => 'محترف'],
        'price_cents' => 0,
        'duration_months' => 1,
        'max_players' => '',
        'max_coaches' => null,
    ]);

    expect($data->maxPlayers)->toBeNull()
        ->and($data->maxCoaches)->toBeNull()
        ->and($data->isActive)->toBeTrue();
});
