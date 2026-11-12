<?php

use App\Domain\Notification\Jobs\SendDueSubscriptionNotificationsJob;
use App\Domain\Shared\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Notification\NotificationFixture;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

it('runs with no current club and notifies only its own club', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $a = TenantFixture::create();
    $b = TenantFixture::create();
    NotificationFixture::subscription($a);
    NotificationFixture::subscription($b);

    expect(app(CurrentClub::class)->get())->toBeNull();

    SendDueSubscriptionNotificationsJob::dispatch($a->club->id);

    expect(app(CurrentClub::class)->get())->toBeNull()
        ->and($a->user->notifications()->count())->toBe(2)
        ->and($b->user->notifications()->count())->toBe(0);
});

it('dispatches one job per club from the command', function () {
    Queue::fake();
    TenantFixture::create();
    TenantFixture::create();

    $this->artisan('club:notify-due')
        ->expectsOutputToContain('Dispatched 2')
        ->assertSuccessful();

    Queue::assertPushedTimes(SendDueSubscriptionNotificationsJob::class, 2);
});

it('limits the command to one club with --club', function () {
    Queue::fake();
    $a = TenantFixture::create();
    TenantFixture::create();

    $this->artisan('club:notify-due', ['--club' => $a->club->id])->assertSuccessful();

    Queue::assertPushedTimes(SendDueSubscriptionNotificationsJob::class, 1);
    Queue::assertPushed(
        SendDueSubscriptionNotificationsJob::class,
        fn (SendDueSubscriptionNotificationsJob $job): bool => $job->clubId === $a->club->id,
    );
});
