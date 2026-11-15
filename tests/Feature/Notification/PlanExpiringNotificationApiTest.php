<?php

use App\Domain\Notification\Enums\NotificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Notification\NotificationFixture;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

it('shows the plan-expiry reminder translated in english and arabic', function () {
    $tenant = TenantFixture::create();
    $params = ['ends_on' => '2026-06-08', 'days_left' => 7, 'threshold' => 7];
    // The sentence speaks of a `date` and of `days`, the row stores `ends_on` and `days_left`.
    $replacements = [...$params, 'date' => '2026-06-08', 'days' => '7'];

    NotificationFixture::notification($tenant->user, NotificationCode::ClubPlanExpiring->value, $params);

    $english = trans('notifications.club.plan_expiring', $replacements);
    $arabic = trans('notifications.club.plan_expiring', $replacements, 'ar');

    $this->withToken($tenant->token)
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.code', 'club.plan_expiring')
        ->assertJsonPath('data.0.message', $english)
        // No personal data: no player, no user, no plan or club name.
        ->assertJsonPath('data.0.params', $params);

    expect($english)->toContain('7 days')
        ->and($arabic)->toContain('7 أيام');

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->withHeader('Accept-Language', 'ar')
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.message', $arabic);
});

it('uses the singular sentence on the last day, in english and arabic', function () {
    $tenant = TenantFixture::create();
    $params = ['ends_on' => '2026-06-02', 'days_left' => 1, 'threshold' => 1];
    $replacements = [...$params, 'date' => '2026-06-02', 'days' => '1'];

    NotificationFixture::notification($tenant->user, NotificationCode::ClubPlanExpiring->value, $params);

    $english = trans('notifications.club.plan_expiring.one', $replacements);
    $arabic = trans('notifications.club.plan_expiring.one', $replacements, 'ar');

    expect($english)->toContain('1 day')
        ->and($english)->not->toContain(':date')
        ->and($arabic)->toContain('يوم واحد')
        ->and($arabic)->not->toContain(':date');

    $this->withToken($tenant->token)
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.message', $english);

    $this->app['auth']->forgetGuards();

    $this->withToken($tenant->token)
        ->withHeader('Accept-Language', 'ar')
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.message', $arabic);
});
