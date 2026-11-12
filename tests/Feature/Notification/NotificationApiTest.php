<?php

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Notification\Enums\NotificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Tests\Support\Notification\NotificationFixture;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/**
 * @param  array<string, mixed>  $params
 */
function ntfBell(TenantFixture $tenant, string $code, array $params = []): DatabaseNotification
{
    return NotificationFixture::notification($tenant->user, $code, $params);
}

it('lists the newest notifications first and filters the unread ones', function () {
    $tenant = TenantFixture::create();

    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));
    $older = ntfBell($tenant, 'club.welcome', ['club_name' => 'Old Club']);

    Carbon::setTestNow(Carbon::parse('2026-06-02 09:00:00', 'UTC'));
    $newer = ntfBell($tenant, 'club.welcome', ['club_name' => 'New Club']);
    $newer->markAsRead();

    $this->withToken($tenant->token)
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonPath('meta.total', 2);

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson('/api/v1/notifications?unread=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $older->id);
});

it('paginates the list like the other endpoints', function () {
    $tenant = TenantFixture::create();

    foreach ([1, 2, 3] as $day) {
        Carbon::setTestNow(Carbon::parse("2026-06-0{$day} 09:00:00", 'UTC'));
        ntfBell($tenant, 'club.welcome', ['club_name' => "Club {$day}"]);
    }

    $this->withToken($tenant->token)
        ->getJson('/api/v1/notifications?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 2);

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson('/api/v1/notifications?per_page=500')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

it('returns the unread count and marks a notification as read idempotently', function () {
    $tenant = TenantFixture::create();
    $first = ntfBell($tenant, 'club.welcome', ['club_name' => 'A']);
    ntfBell($tenant, 'club.welcome', ['club_name' => 'B']);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertExactJson(['data' => ['unread' => 2]]);

    $this->app['auth']->forgetGuards();

    $readAt = $this->withToken($tenant->token)
        ->postJson("/api/v1/notifications/{$first->id}/read")
        ->assertOk()
        ->json('data.read_at');

    expect($readAt)->toBeString();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson("/api/v1/notifications/{$first->id}/read")
        ->assertOk()
        ->assertJsonPath('data.read_at', $readAt);
});

it('marks every unread notification as read', function () {
    $tenant = TenantFixture::create();
    ntfBell($tenant, 'club.welcome', ['club_name' => 'A']);
    ntfBell($tenant, 'club.welcome', ['club_name' => 'B']);

    $this->withToken($tenant->token)
        ->postJson('/api/v1/notifications/read-all')
        ->assertOk()
        ->assertExactJson(['data' => ['marked' => 2]]);

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson('/api/v1/notifications/read-all')
        ->assertOk()
        ->assertExactJson(['data' => ['marked' => 0]]);

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.unread', 0);
});

it('returns 404 for another user notification, in the same club or not', function () {
    $tenant = TenantFixture::create();
    $staff = NotificationFixture::userWithRole($tenant, RoleName::Staff);
    $staffNotification = NotificationFixture::notification($staff, 'club.welcome', ['club_name' => 'Hidden']);

    $other = TenantFixture::create();
    $otherNotification = NotificationFixture::notification($other->user, 'club.welcome', ['club_name' => 'Elsewhere']);

    $this->withToken($tenant->token)
        ->postJson("/api/v1/notifications/{$staffNotification->id}/read")
        ->assertNotFound();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson("/api/v1/notifications/{$otherNotification->id}/read")
        ->assertNotFound();

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->postJson('/api/v1/notifications/not-a-uuid/read')
        ->assertNotFound();
});

it('translates the message and formats the amount in english and arabic', function () {
    $tenant = TenantFixture::create();
    $params = [
        'subscription_id' => 5,
        'player_id' => 3,
        'player_name' => 'Ali Hassan',
        'plan_name' => 'Gold Monthly',
        'outstanding_cents' => 50050,
        'currency' => 'EGP',
    ];

    ntfBell($tenant, NotificationCode::SubscriptionUnpaid->value, $params);

    $expected = trans('notifications.subscription.unpaid', [...$params, 'amount' => '500.50 EGP']);

    $this->withToken($tenant->token)
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.code', 'subscription.unpaid')
        ->assertJsonPath('data.0.message', $expected);

    expect($expected)->toContain('500.50 EGP');

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->withHeader('Accept-Language', 'ar')
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath(
            'data.0.message',
            trans('notifications.subscription.unpaid', [...$params, 'amount' => '500.50 EGP'], 'ar'),
        );
});

it('gives a coach an empty list and rejects anonymous callers', function () {
    $coach = TenantFixture::create(RoleName::Coach);

    $this->withToken($coach->token)
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->flushHeaders();
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/notifications')->assertUnauthorized();
});
