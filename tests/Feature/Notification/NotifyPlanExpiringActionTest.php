<?php

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Enums\ClubStatus;
use App\Domain\Notification\Actions\NotifyPlanExpiringAction;
use App\Domain\Notification\DTOs\NotifyPlanExpiringResult;
use App\Domain\Notification\Enums\PlanExpirySkipReason;
use App\Domain\Notification\Notifications\PlanExpiringNotification;
use App\Domain\Shared\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Notification\NotificationFixture;
use Tests\Support\Notification\PlanExpiryFixture;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

function schRunPlanExpiry(TenantFixture $tenant): NotifyPlanExpiringResult
{
    return app(NotifyPlanExpiringAction::class)->execute($tenant->club);
}

it('sends one reminder to the owner for a plan ending in seven days', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $tenant = PlanExpiryFixture::tenant('2026-06-08 09:00:00');

    $result = schRunPlanExpiry($tenant);
    $notification = $tenant->user->notifications()->firstOrFail();

    expect($result->created)->toBe(1)
        ->and($result->skipped)->toBeNull()
        ->and($result->threshold)->toBe(7)
        ->and($notification->type)->toBe(PlanExpiringNotification::class)
        ->and($notification->data['code'])->toBe('club.plan_expiring')
        ->and($notification->data['params'])->toEqual([
            'ends_on' => '2026-06-08',
            'days_left' => 7,
            'threshold' => 7,
        ])
        ->and($notification->data['dedup_key'])
        ->toBe("club.plan_expiring:{$tenant->club->id}:2026-06-08:7")
        ->and(app(CurrentClub::class)->get())->toBeNull();
});

it('creates nothing on a second run for the same end date', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $tenant = PlanExpiryFixture::tenant('2026-06-08 09:00:00');

    expect(schRunPlanExpiry($tenant)->created)->toBe(1);

    $second = schRunPlanExpiry($tenant);

    expect($second->created)->toBe(0)
        ->and($second->skipped)->toBe(PlanExpirySkipReason::AlreadySent)
        ->and($tenant->user->notifications()->count())->toBe(1);
});

it('sends the one-day reminder at one day left, after the seven-day one', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $tenant = PlanExpiryFixture::tenant('2026-06-08 09:00:00');

    expect(schRunPlanExpiry($tenant)->threshold)->toBe(7);

    Carbon::setTestNow(Carbon::parse('2026-06-07 09:00:00', 'UTC'));

    $second = schRunPlanExpiry($tenant);

    $keys = $tenant->user->notifications()->get()
        ->map(fn ($notification): string => $notification->data['dedup_key'])
        ->all();

    expect($second->created)->toBe(1)
        ->and($second->threshold)->toBe(1)
        ->and($keys)->toBe([
            "club.plan_expiring:{$tenant->club->id}:2026-06-08:1",
            "club.plan_expiring:{$tenant->club->id}:2026-06-08:7",
        ]);
});

it('sends only the one-day reminder at one day left when nothing was sent yet', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-07 09:00:00', 'UTC'));

    $tenant = PlanExpiryFixture::tenant('2026-06-08 09:00:00');

    $result = schRunPlanExpiry($tenant);

    expect($result->threshold)->toBe(1)
        ->and($result->created)->toBe(1)
        ->and($tenant->user->notifications()->count())->toBe(1);
});

it('never notifies a staff member, a coach or a referee', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $tenant = PlanExpiryFixture::tenant('2026-06-08 09:00:00');
    $staff = NotificationFixture::userWithRole($tenant, RoleName::Staff);
    $coach = NotificationFixture::userWithRole($tenant, RoleName::Coach);
    $referee = NotificationFixture::userWithRole($tenant, RoleName::Referee);

    expect(schRunPlanExpiry($tenant)->created)->toBe(1)
        ->and($tenant->user->notifications()->count())->toBe(1)
        ->and($staff->notifications()->count())->toBe(0)
        ->and($coach->notifications()->count())->toBe(0)
        ->and($referee->notifications()->count())->toBe(0);
});

it('skips a club without an owner', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $tenant = PlanExpiryFixture::tenant('2026-06-08 09:00:00', [], RoleName::Staff);

    expect(schRunPlanExpiry($tenant)->skipped)->toBe(PlanExpirySkipReason::NoOwner)
        ->and($tenant->user->notifications()->count())->toBe(0);
});

it('skips suspended clubs, clubs without a plan, without an end date and expired plans', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $cases = [
        'suspended' => [
            PlanExpiryFixture::tenant('2026-06-08 09:00:00', ['status' => ClubStatus::Suspended]),
            PlanExpirySkipReason::ClubSuspended,
        ],
        'no plan' => [
            PlanExpiryFixture::tenant('2026-06-08 09:00:00', ['plan_id' => null]),
            PlanExpirySkipReason::NoPlan,
        ],
        'no end date' => [
            PlanExpiryFixture::tenant(null),
            PlanExpirySkipReason::NoEndDate,
        ],
        'already expired' => [
            PlanExpiryFixture::tenant('2026-05-30 09:00:00'),
            PlanExpirySkipReason::PlanExpired,
        ],
    ];

    foreach ($cases as $label => [$tenant, $reason]) {
        $result = schRunPlanExpiry($tenant);

        expect($result->created)->toBe(0, $label)
            ->and($result->skipped)->toBe($reason, $label)
            ->and($tenant->user->notifications()->count())->toBe(0, $label);
    }
});

it('skips a plan that is further away than every threshold', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $tenant = PlanExpiryFixture::tenant('2026-06-20 09:00:00');

    expect(schRunPlanExpiry($tenant)->skipped)->toBe(PlanExpirySkipReason::NotDue)
        ->and($tenant->user->notifications()->count())->toBe(0);
});

it('reminds again after the plan is renewed with a new end date', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $tenant = PlanExpiryFixture::tenant('2026-06-08 09:00:00');

    expect(schRunPlanExpiry($tenant)->created)->toBe(1);

    PlanExpiryFixture::renew($tenant, '2026-07-01 09:00:00');

    Carbon::setTestNow(Carbon::parse('2026-06-24 09:00:00', 'UTC'));

    $renewed = schRunPlanExpiry($tenant);

    expect($renewed->created)->toBe(1)
        ->and($renewed->threshold)->toBe(7)
        ->and($tenant->user->notifications()->count())->toBe(2);
});

it('never creates a notification for the owner of another club', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $a = PlanExpiryFixture::tenant('2026-06-08 09:00:00');
    $b = PlanExpiryFixture::tenant('2026-06-08 09:00:00');

    schRunPlanExpiry($a);

    expect($a->user->notifications()->count())->toBe(1)
        ->and($b->user->notifications()->count())->toBe(0);
});

it('counts the days left on the calendar dates of the subscriptions timezone', function () {
    $timezone = (string) config('subscriptions.timezone');

    // Late in the Cairo evening: both end dates below are already the next day in Cairo while
    // they are still the previous day in UTC, so a UTC count would see one day less.
    $now = Carbon::parse('2026-06-01 23:30', $timezone);

    Carbon::setTestNow($now->copy()->utc());

    $farEndsAt = $now->copy()->addDays(8)->setTime(0, 30);
    $dueEndsAt = $now->copy()->addDays(7)->setTime(0, 30);

    expect($farEndsAt->copy()->utc()->toDateString())->not->toBe($farEndsAt->toDateString());

    $far = PlanExpiryFixture::tenant($farEndsAt->copy()->utc()->format('Y-m-d H:i:s'));
    $due = PlanExpiryFixture::tenant($dueEndsAt->copy()->utc()->format('Y-m-d H:i:s'));

    $farResult = schRunPlanExpiry($far);
    $dueResult = schRunPlanExpiry($due);

    expect($farResult->skipped)->toBe(PlanExpirySkipReason::NotDue)
        ->and($far->user->notifications()->count())->toBe(0)
        ->and($dueResult->threshold)->toBe(7)
        ->and($due->user->notifications()->firstOrFail()->data['params'])->toEqual([
            'ends_on' => '2026-06-08',
            'days_left' => 7,
            'threshold' => 7,
        ]);
});

it('uses the configured thresholds', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));
    config(['club_notifications.plan_expiry_reminder_days' => [3]]);

    $tenant = PlanExpiryFixture::tenant('2026-06-03 09:00:00');

    expect(schRunPlanExpiry($tenant)->threshold)->toBe(3)
        ->and($tenant->user->notifications()->firstOrFail()->data['params']['threshold'])->toBe(3);
});
