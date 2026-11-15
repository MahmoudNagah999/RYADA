<?php

use App\Domain\Club\Enums\ClubStatus;
use App\Domain\Notification\Jobs\SendPlanExpiryNotificationsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Notification\PlanExpiryFixture;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC')));

afterEach(fn () => Carbon::setTestNow());

it('dispatches one job per eligible club', function () {
    Queue::fake();

    $sevenDays = PlanExpiryFixture::tenant('2026-06-08 09:00:00');
    $oneDay = PlanExpiryFixture::tenant('2026-06-02 09:00:00');

    $this->artisan('clubs:notify-plan-expiry')
        ->expectsOutputToContain('Dispatched 2')
        ->assertSuccessful();

    Queue::assertPushedTimes(SendPlanExpiryNotificationsJob::class, 2);
    Queue::assertPushed(
        SendPlanExpiryNotificationsJob::class,
        fn (SendPlanExpiryNotificationsJob $job): bool => $job->clubId === $sevenDays->club->id,
    );
    Queue::assertPushed(
        SendPlanExpiryNotificationsJob::class,
        fn (SendPlanExpiryNotificationsJob $job): bool => $job->clubId === $oneDay->club->id,
    );
});

it('skips suspended, planless, endless, far away and expired clubs', function () {
    Queue::fake();

    PlanExpiryFixture::tenant('2026-06-08 09:00:00', ['status' => ClubStatus::Suspended]);
    PlanExpiryFixture::tenant('2026-06-08 09:00:00', ['plan_id' => null]);
    PlanExpiryFixture::tenant(null);
    PlanExpiryFixture::tenant('2026-09-01 09:00:00');
    PlanExpiryFixture::tenant('2026-05-20 09:00:00');

    $this->artisan('clubs:notify-plan-expiry')
        ->expectsOutputToContain('Dispatched 0')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('dispatches for one club only with --club', function () {
    Queue::fake();

    $a = PlanExpiryFixture::tenant('2026-06-08 09:00:00');
    PlanExpiryFixture::tenant('2026-06-08 09:00:00');

    $this->artisan('clubs:notify-plan-expiry', ['--club' => $a->club->id])
        ->expectsOutputToContain('Dispatched 1')
        ->assertSuccessful();

    Queue::assertPushedTimes(SendPlanExpiryNotificationsJob::class, 1);
    Queue::assertPushed(
        SendPlanExpiryNotificationsJob::class,
        fn (SendPlanExpiryNotificationsJob $job): bool => $job->clubId === $a->club->id,
    );
});

it('dispatches nothing with --club when the club is not due', function () {
    Queue::fake();

    $tenant = PlanExpiryFixture::tenant('2026-06-08 09:00:00', ['status' => ClubStatus::Suspended]);

    $this->artisan('clubs:notify-plan-expiry', ['--club' => $tenant->club->id])
        ->expectsOutputToContain('Dispatched 0')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('fails with --club when the club does not exist', function () {
    Queue::fake();

    $this->artisan('clubs:notify-plan-expiry', ['--club' => 999999])
        ->expectsOutputToContain('was not found')
        ->assertFailed();

    Queue::assertNothingPushed();
});
