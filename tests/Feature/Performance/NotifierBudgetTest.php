<?php

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Notification\Actions\NotifyDueSubscriptionsAction;
use App\Domain\Notification\Actions\NotifyPlanExpiringAction;
use App\Domain\Notification\DTOs\NotifyDueSubscriptionsResult;
use App\Domain\Notification\DTOs\NotifyPlanExpiringResult;
use App\Domain\Notification\Enums\PlanExpirySkipReason;
use App\Domain\Notification\Support\NotificationSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Notification\NotificationFixture;
use Tests\Support\Notification\PlanExpiryFixture;
use Tests\Support\Performance\QueryCounter;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Notifier query budgets
|--------------------------------------------------------------------------
|
| The notice dedup lookup used to be one `notifications()->exists()` per
| subscription and per recipient. A run over N due subscriptions with R
| recipients therefore cost about N x R extra queries even when nothing
| was sent.
|
| These tests measure the SECOND run of each notifier (everything already
| sent) and assert its query count does not grow with the number of due
| subscriptions (or with the number of users in the club). They pin the
| measured value as a ceiling too, so a regression is caught even when it
| is constant but more expensive than before.
|
| The first run still inserts one row per notification it creates, so its
| count grows with the number of notifications by design. Only a ceiling
| is pinned for that case.
|
*/

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * A club with the two default recipients (Owner + Staff).
 *
 * @return array{0: TenantFixture, 1: User}
 */
function nbdDueTenant(): array
{
    $tenant = TenantFixture::create();

    return [$tenant, NotificationFixture::userWithRole($tenant, RoleName::Staff)];
}

/**
 * Seed a deterministic mix of due subscriptions:
 *   - one in three is only expiring (fully paid),
 *   - one in three is expiring and unpaid (partial payment),
 *   - one in three is only unpaid (outside the expiring window).
 */
function nbdSeedDueSubscriptions(TenantFixture $tenant, int $count): void
{
    foreach (range(1, $count) as $index) {
        if ($index % 3 === 1) {
            $subscription = NotificationFixture::subscription($tenant);
            NotificationFixture::payment($tenant, $subscription, 50000);
        } elseif ($index % 3 === 2) {
            $subscription = NotificationFixture::subscription($tenant);
            NotificationFixture::payment($tenant, $subscription, 10000);
        } else {
            NotificationFixture::subscription($tenant, ['ends_on' => '2026-08-01']);
        }
    }
}

/**
 * A club whose SaaS plan ends in one day (threshold "1"), with the extra users asked for.
 * Only the Owner is a recipient of the plan reminder.
 *
 * @return array{tenant: TenantFixture, staff: User|null, coach: User|null}
 */
function nbdPlanTenant(int $users): array
{
    $tenant = PlanExpiryFixture::tenant('2026-06-02 09:00:00');

    return [
        'tenant' => $tenant,
        'staff' => $users >= 2 ? NotificationFixture::userWithRole($tenant, RoleName::Staff) : null,
        'coach' => $users >= 3 ? NotificationFixture::userWithRole($tenant, RoleName::Coach) : null,
    ];
}

/**
 * Mark the "7 days left" reminder as already sent, so only the "1 day left" one is pending.
 */
function nbdMarkSevenDayReminderSent(TenantFixture $tenant): void
{
    NotificationFixture::notification(
        $tenant->user,
        'club.plan_expiring',
        ['ends_on' => '2026-06-02', 'days_left' => 7, 'threshold' => 7],
        NotificationSchedule::planExpiringDedupKey($tenant->club->id, '2026-06-02', 7),
    );
}

/**
 * @param  array{count: int, queries: list<string>}  $small
 * @param  array{count: int, queries: list<string>}  $large
 */
function nbdAssertConstant(string $label, array $small, array $large): void
{
    expect($large['count'])->toBe(
        $small['count'],
        sprintf(
            '%s: the second run grew with the number of due rows (%d queries vs %d). SQL of the larger run: %s',
            $label,
            $small['count'],
            $large['count'],
            implode(' | ', $large['queries']),
        ),
    );
}

/**
 * @param  array{count: int, queries: list<string>}  $measurement
 */
function nbdCeiling(string $label, array $measurement, int $ceiling, string $note): void
{
    expect($measurement['count'])->toBeLessThanOrEqual(
        $ceiling,
        sprintf(
            '%s: %d queries, above the pinned ceiling of %d (%s). SQL: %s',
            $label,
            $measurement['count'],
            $ceiling,
            $note,
            implode(' | ', $measurement['queries']),
        ),
    );
}

it('keeps the due-subscription dedup lookup constant in the number of subscriptions', function (): void {
    [$smallTenant, $smallStaff] = nbdDueTenant();
    nbdSeedDueSubscriptions($smallTenant, 3);
    $smallAction = app(NotifyDueSubscriptionsAction::class);

    $smallFirstResult = null;
    $smallFirst = QueryCounter::measure(function () use ($smallAction, $smallTenant, &$smallFirstResult): void {
        $smallFirstResult = $smallAction->execute($smallTenant->club);
    });

    $smallSecondResult = null;
    $smallSecond = QueryCounter::measure(function () use ($smallAction, $smallTenant, &$smallSecondResult): void {
        $smallSecondResult = $smallAction->execute($smallTenant->club);
    });

    // 3 subscriptions: 2 expiring and 2 unpaid, each sent to the two recipients.
    /** @var NotifyDueSubscriptionsResult $smallFirstResult */
    /** @var NotifyDueSubscriptionsResult $smallSecondResult */
    expect($smallFirstResult)->toBeInstanceOf(NotifyDueSubscriptionsResult::class)
        ->and($smallFirstResult->expiring)->toBe(4)
        ->and($smallFirstResult->unpaid)->toBe(4)
        ->and($smallSecondResult)->toBeInstanceOf(NotifyDueSubscriptionsResult::class)
        ->and($smallSecondResult->total())->toBe(0)
        ->and($smallTenant->user->notifications()->count())->toBe(4)
        ->and($smallStaff->notifications()->count())->toBe(4);

    [$largeTenant, $largeStaff] = nbdDueTenant();
    nbdSeedDueSubscriptions($largeTenant, 12);
    $largeAction = app(NotifyDueSubscriptionsAction::class);

    $largeFirstResult = null;
    $largeFirst = QueryCounter::measure(function () use ($largeAction, $largeTenant, &$largeFirstResult): void {
        $largeFirstResult = $largeAction->execute($largeTenant->club);
    });

    $largeSecondResult = null;
    $largeSecond = QueryCounter::measure(function () use ($largeAction, $largeTenant, &$largeSecondResult): void {
        $largeSecondResult = $largeAction->execute($largeTenant->club);
    });

    // 12 subscriptions: 8 expiring and 8 unpaid, each sent to the two recipients.
    /** @var NotifyDueSubscriptionsResult $largeFirstResult */
    /** @var NotifyDueSubscriptionsResult $largeSecondResult */
    expect($largeFirstResult)->toBeInstanceOf(NotifyDueSubscriptionsResult::class)
        ->and($largeFirstResult->expiring)->toBe(16)
        ->and($largeFirstResult->unpaid)->toBe(16)
        ->and($largeSecondResult)->toBeInstanceOf(NotifyDueSubscriptionsResult::class)
        ->and($largeSecondResult->total())->toBe(0)
        ->and($largeTenant->user->notifications()->count())->toBe(16)
        ->and($largeStaff->notifications()->count())->toBe(16);

    nbdAssertConstant('NotifyDueSubscriptionsAction second run (3 vs 12 subscriptions)', $smallSecond, $largeSecond);

    // The second run resolves the recipients once and runs one dedup query per page and pass,
    // whatever the number of due subscriptions on the page.
    nbdCeiling('NotifyDueSubscriptionsAction second run (3 subscriptions)', $smallSecond, 11, 'measured on 2026-11-22');
    nbdCeiling('NotifyDueSubscriptionsAction second run (12 subscriptions)', $largeSecond, 11, 'measured on 2026-11-22');

    // The first run also inserts one notification per send, so it grows linearly on purpose:
    // 11 shared queries + 8 inserts for 3 subscriptions, 11 + 32 inserts for 12.
    nbdCeiling('NotifyDueSubscriptionsAction first run (3 subscriptions)', $smallFirst, 19, 'measured on 2026-11-22; grows with created notifications');
});

it('keeps the plan-expiry dedup lookup constant and sends only to the owner', function (): void {
    $oneUser = nbdPlanTenant(1);
    nbdMarkSevenDayReminderSent($oneUser['tenant']);
    $action = app(NotifyPlanExpiringAction::class);

    $oneFirstResult = null;
    $oneFirst = QueryCounter::measure(function () use ($action, $oneUser, &$oneFirstResult): void {
        $oneFirstResult = $action->execute($oneUser['tenant']->club);
    });

    $oneSecondResult = null;
    $oneSecond = QueryCounter::measure(function () use ($action, $oneUser, &$oneSecondResult): void {
        $oneSecondResult = $action->execute($oneUser['tenant']->club);
    });

    /** @var NotifyPlanExpiringResult $oneFirstResult */
    /** @var NotifyPlanExpiringResult $oneSecondResult */
    expect($oneFirstResult)->toBeInstanceOf(NotifyPlanExpiringResult::class)
        ->and($oneFirstResult->created)->toBe(1)
        ->and($oneFirstResult->threshold)->toBe(1)
        ->and($oneSecondResult)->toBeInstanceOf(NotifyPlanExpiringResult::class)
        ->and($oneSecondResult->created)->toBe(0)
        ->and($oneSecondResult->skipped)->toBe(PlanExpirySkipReason::AlreadySent)
        ->and($oneUser['tenant']->user->notifications()->count())->toBe(2);

    $threeUsers = nbdPlanTenant(3);
    nbdMarkSevenDayReminderSent($threeUsers['tenant']);

    $threeFirstResult = null;
    $threeFirst = QueryCounter::measure(function () use ($action, $threeUsers, &$threeFirstResult): void {
        $threeFirstResult = $action->execute($threeUsers['tenant']->club);
    });

    $threeSecondResult = null;
    $threeSecond = QueryCounter::measure(function () use ($action, $threeUsers, &$threeSecondResult): void {
        $threeSecondResult = $action->execute($threeUsers['tenant']->club);
    });

    // Staff and Coach are never recipients of the plan reminder, so the extra users add no rows.
    /** @var NotifyPlanExpiringResult $threeFirstResult */
    /** @var NotifyPlanExpiringResult $threeSecondResult */
    expect($threeFirstResult)->toBeInstanceOf(NotifyPlanExpiringResult::class)
        ->and($threeFirstResult->created)->toBe(1)
        ->and($threeSecondResult)->toBeInstanceOf(NotifyPlanExpiringResult::class)
        ->and($threeSecondResult->created)->toBe(0)
        ->and($threeUsers['tenant']->user->notifications()->count())->toBe(2)
        ->and($threeUsers['staff'])->not->toBeNull()
        ->and($threeUsers['staff']->notifications()->count())->toBe(0)
        ->and($threeUsers['coach'])->not->toBeNull()
        ->and($threeUsers['coach']->notifications()->count())->toBe(0);

    nbdAssertConstant('NotifyPlanExpiringAction second run (1 vs 3 users)', $oneSecond, $threeSecond);

    // One recipient resolution + one dedup query, whatever the number of users in the club.
    nbdCeiling('NotifyPlanExpiringAction second run (1 user)', $oneSecond, 2, 'measured on 2026-11-22');
    nbdCeiling('NotifyPlanExpiringAction second run (3 users)', $threeSecond, 2, 'measured on 2026-11-22');
    nbdCeiling('NotifyPlanExpiringAction first run (1 user)', $oneFirst, 3, 'measured on 2026-11-22; grows one insert per recipient');
});
