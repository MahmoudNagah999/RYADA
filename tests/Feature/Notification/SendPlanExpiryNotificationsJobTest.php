<?php

use App\Domain\Notification\Jobs\SendPlanExpiryNotificationsJob;
use App\Domain\Shared\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Tests\Support\Notification\PlanExpiryFixture;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

it('runs with no current club and reminds only its own club', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $a = PlanExpiryFixture::tenant('2026-06-08 09:00:00');
    $b = PlanExpiryFixture::tenant('2026-06-08 09:00:00');

    expect(app(CurrentClub::class)->get())->toBeNull();

    SendPlanExpiryNotificationsJob::dispatch($a->club->id);

    expect(app(CurrentClub::class)->get())->toBeNull()
        ->and($a->user->notifications()->count())->toBe(1)
        ->and($b->user->notifications()->count())->toBe(0);
});

it('drops the job silently when the club no longer exists', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));

    $tenant = PlanExpiryFixture::tenant('2026-06-08 09:00:00');
    $clubId = $tenant->club->id;

    $tenant->club->delete();

    SendPlanExpiryNotificationsJob::dispatch($clubId);

    expect(DatabaseNotification::query()->count())->toBe(0);
});
