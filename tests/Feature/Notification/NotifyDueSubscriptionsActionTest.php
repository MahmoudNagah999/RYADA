<?php

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Notification\Actions\NotifyDueSubscriptionsAction;
use App\Domain\Notification\DTOs\NotifyDueSubscriptionsResult;
use App\Domain\Shared\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Notification\NotificationFixture;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

function ntfRun(TenantFixture $tenant): NotifyDueSubscriptionsResult
{
    return app(NotifyDueSubscriptionsAction::class)->execute($tenant->club);
}

it('notifies every owner and staff of the club and nobody else', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $staff = NotificationFixture::userWithRole($tenant, RoleName::Staff);
    $coach = NotificationFixture::userWithRole($tenant, RoleName::Coach);
    NotificationFixture::subscription($tenant);

    $result = ntfRun($tenant);

    // The counters count notifications, so one due subscription times two recipients.
    expect($result->expiring)->toBe(2)
        ->and($result->unpaid)->toBe(2)
        ->and($tenant->user->notifications()->count())->toBe(2)
        ->and($staff->notifications()->count())->toBe(2)
        ->and($coach->notifications()->count())->toBe(0)
        ->and(app(CurrentClub::class)->get())->toBeNull();
});

it('sends the expiring notification once per subscription and end date', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $subscription = NotificationFixture::subscription($tenant);
    // Fully paid: only the expiring notification is due.
    NotificationFixture::payment($tenant, $subscription, 50000);

    expect(ntfRun($tenant)->expiring)->toBe(1);

    $notification = $tenant->user->notifications()->firstOrFail();

    expect($notification->data['code'])->toBe('subscription.expiring')
        ->and($notification->data['params'])->toEqual([
            'subscription_id' => $subscription->id,
            'player_id' => $subscription->player_id,
            'player_name' => 'Ali Hassan',
            'plan_name' => 'Gold Monthly',
            'ends_on' => '2026-06-05',
            'days_left' => 4,
        ])
        ->and($notification->data['dedup_key'])
        ->toBe("subscription.expiring:{$subscription->id}:2026-06-05");

    $second = ntfRun($tenant);

    expect($second->total())->toBe(0)
        ->and($tenant->user->notifications()->count())->toBe(1);
});

it('never chases an unpaid subscription before its grace period ends', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $tooFresh = NotificationFixture::subscription($tenant, [
        'starts_on' => '2026-05-30',
        'ends_on' => '2026-08-01',
    ]);
    $boundary = NotificationFixture::subscription($tenant, [
        'starts_on' => '2026-05-29',
        'ends_on' => '2026-08-01',
    ]);
    $old = NotificationFixture::subscription($tenant, [
        'starts_on' => '2026-05-01',
        'ends_on' => '2026-08-01',
    ]);

    $result = ntfRun($tenant);

    $notified = $tenant->user->notifications()->get()
        ->map(fn ($notification): int => $notification->data['params']['subscription_id'])
        ->sort()
        ->values()
        ->all();

    expect($result->unpaid)->toBe(2)
        ->and($result->expiring)->toBe(0)
        ->and($notified)->toBe(collect([$boundary->id, $old->id])->sort()->values()->all())
        ->and($notified)->not->toContain($tooFresh->id);
});

it('repeats the unpaid notification only in a new iso week', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();
    $subscription = NotificationFixture::subscription($tenant, [
        'starts_on' => '2026-05-01',
        'ends_on' => '2026-08-01',
    ]);

    expect(ntfRun($tenant)->unpaid)->toBe(1);

    // Same ISO week, another day: the dedup key is unchanged.
    Carbon::setTestNow(Carbon::parse('2026-06-04 09:00:00', 'UTC'));
    expect(ntfRun($tenant)->unpaid)->toBe(0);

    // Next ISO week.
    Carbon::setTestNow(Carbon::parse('2026-06-08 09:00:00', 'UTC'));
    expect(ntfRun($tenant)->unpaid)->toBe(1);

    $keys = $tenant->user->notifications()->get()->map(fn ($notification): string => $notification->data['dedup_key'])->all();

    expect($tenant->user->notifications()->count())->toBe(2)
        ->and($keys)->toBe([
            "subscription.unpaid:{$subscription->id}:2026-W24",
            "subscription.unpaid:{$subscription->id}:2026-W23",
        ]);
});

it('never notifies a fully paid, cancelled or refunded subscription', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $tenant = TenantFixture::create();

    $paid = NotificationFixture::subscription($tenant, ['ends_on' => '2026-08-01']);
    NotificationFixture::payment($tenant, $paid, 50000);

    NotificationFixture::subscription($tenant, [
        'ends_on' => '2026-08-01',
        'cancelled_at' => now(),
    ]);

    $refunded = NotificationFixture::subscription($tenant, ['ends_on' => '2026-08-01']);
    $payment = NotificationFixture::payment($tenant, $refunded, 50000);
    NotificationFixture::refund($tenant, $payment, 50000);

    $result = ntfRun($tenant);

    expect($result->total())->toBe(0)
        ->and($tenant->user->notifications()->count())->toBe(0);
});

it('never creates rows for the users of another club', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $a = TenantFixture::create();
    $b = TenantFixture::create();
    NotificationFixture::subscription($a);
    NotificationFixture::subscription($b);

    ntfRun($a);

    expect($a->user->notifications()->count())->toBe(2)
        ->and($b->user->notifications()->count())->toBe(0);
});

it('walks every page with the configured page size without touching the request', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));
    config(['club_notifications.page_size' => 2]);

    $tenant = TenantFixture::create();

    // Five unpaid subscriptions, all outside the expiring window.
    foreach (range(1, 5) as $ignored) {
        NotificationFixture::subscription($tenant, ['ends_on' => '2026-08-01']);
    }

    expect(request()->query('page'))->toBeNull();

    expect(ntfRun($tenant)->unpaid)->toBe(5)
        ->and($tenant->user->notifications()->count())->toBe(5)
        ->and(request()->query('page'))->toBeNull();

    expect(ntfRun($tenant)->total())->toBe(0)
        ->and($tenant->user->notifications()->count())->toBe(5)
        ->and(request()->query('page'))->toBeNull();
});
