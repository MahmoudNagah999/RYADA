<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Membership\Actions\CreateMembershipPlanAction;
use App\Domain\Membership\Actions\UpdateMembershipPlanAction;
use App\Domain\Membership\DTOs\CreateMembershipPlanData;
use App\Domain\Membership\DTOs\UpdateMembershipPlanData;
use App\Domain\Membership\Models\MembershipPlan;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Domain\Sport\Models\ClubSport;
use App\Domain\Sport\Models\Sport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

function planClubWithSport(): array
{
    $tenant = TenantFixture::create();
    $sport = Sport::factory()->create();
    app(CurrentClub::class)->runAs($tenant->club, fn () => ClubSport::query()->create(['sport_id' => $sport->id]));

    return [$tenant, $sport];
}

function inClub(object $tenant, Closure $callback): mixed
{
    return app(CurrentClub::class)->runAs($tenant->club, $callback);
}

it('creates a plan for an offered sport and logs it', function () {
    [$tenant, $sport] = planClubWithSport();

    $plan = inClub($tenant, fn () => app(CreateMembershipPlanAction::class)->execute(
        new CreateMembershipPlanData($sport->id, 'Monthly', 50000, 1),
    ));

    expect($plan->club_id)->toBe($tenant->club->id)
        ->and($plan->price_cents)->toBe(50000)
        ->and($plan->currency)->toBe('EGP');

    $activity = Activity::query()->where('event', 'membership_plan.created')->firstOrFail();
    expect($activity->club_id)->toBe($tenant->club->id)
        ->and($activity->properties->get('sport'))->toBe($sport->slug);
});

it('rejects a sport the club does not offer', function () {
    $tenant = TenantFixture::create();
    $sport = Sport::factory()->create();

    inClub($tenant, fn () => app(CreateMembershipPlanAction::class)->execute(
        new CreateMembershipPlanData($sport->id, 'Monthly', 50000, 1),
    ));
})->throws(BusinessRuleException::class);

it('rejects an inactive sport', function () {
    [$tenant, $sport] = planClubWithSport();
    $sport->update(['is_active' => false]);

    try {
        inClub($tenant, fn () => app(CreateMembershipPlanAction::class)->execute(
            new CreateMembershipPlanData($sport->id, 'Monthly', 50000, 1),
        ));
    } catch (BusinessRuleException $e) {
        expect($e->getMessage())->not->toBeEmpty();

        return;
    }

    $this->fail('Expected BusinessRuleException');
});

it('rejects a duplicate name for the same sport but allows it in another club', function () {
    [$tenant, $sport] = planClubWithSport();
    $create = fn () => app(CreateMembershipPlanAction::class)->execute(
        new CreateMembershipPlanData($sport->id, 'Monthly', 50000, 1),
    );

    inClub($tenant, $create);

    expect(fn () => inClub($tenant, $create))->toThrow(BusinessRuleException::class);

    $other = TenantFixture::create();
    inClub($other, fn () => ClubSport::query()->create(['sport_id' => $sport->id]));

    expect(inClub($other, $create))->toBeInstanceOf(MembershipPlan::class);
});

it('updates a plan, keeps the sport and logs only real changes', function () {
    [$tenant, $sport] = planClubWithSport();
    $plan = MembershipPlan::factory()->create([
        'club_id' => $tenant->club->id,
        'sport_id' => $sport->id,
        'name' => 'Monthly',
        'price_cents' => 50000,
        'duration_months' => 1,
        'is_active' => true,
    ]);

    inClub($tenant, fn () => app(UpdateMembershipPlanAction::class)->execute(
        $plan,
        new UpdateMembershipPlanData('Monthly', 60000, 1, false),
    ));

    expect($plan->fresh()->sport_id)->toBe($sport->id)
        ->and($plan->fresh()->price_cents)->toBe(60000)
        ->and($plan->fresh()->is_active)->toBeFalse();

    $changes = Activity::query()->where('event', 'membership_plan.updated')->firstOrFail()
        ->properties->get('changes');

    expect(array_keys($changes))->toEqualCanonicalizing(['price_cents', 'is_active'])
        ->and($changes['price_cents'])->toEqual(['from' => 50000, 'to' => 60000]);
});

it('does not log an update with no changes', function () {
    [$tenant, $sport] = planClubWithSport();
    $plan = MembershipPlan::factory()->create([
        'club_id' => $tenant->club->id,
        'sport_id' => $sport->id,
        'name' => 'Monthly',
        'price_cents' => 50000,
        'currency' => 'EGP',
        'duration_months' => 1,
        'is_active' => true,
    ]);

    inClub($tenant, fn () => app(UpdateMembershipPlanAction::class)->execute(
        $plan,
        new UpdateMembershipPlanData('Monthly', 50000, 1, true),
    ));

    expect(Activity::query()->where('event', 'membership_plan.updated')->count())->toBe(0);
});

it('rejects renaming to a name already used by another plan of the same sport', function () {
    [$tenant, $sport] = planClubWithSport();
    MembershipPlan::factory()->create(['club_id' => $tenant->club->id, 'sport_id' => $sport->id, 'name' => 'Monthly']);
    $other = MembershipPlan::factory()->create(['club_id' => $tenant->club->id, 'sport_id' => $sport->id, 'name' => 'Yearly']);

    expect(fn () => inClub($tenant, fn () => app(UpdateMembershipPlanAction::class)->execute(
        $other,
        new UpdateMembershipPlanData('Monthly', $other->price_cents, $other->duration_months, true),
    )))->toThrow(BusinessRuleException::class);
});
