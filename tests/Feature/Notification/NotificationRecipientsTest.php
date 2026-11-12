<?php

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Notification\Actions\ResolveNotificationRecipientsAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Notification\NotificationFixture;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

it('returns exactly the owners and staff of the club', function () {
    $tenant = TenantFixture::create(RoleName::Owner);
    $staff = NotificationFixture::userWithRole($tenant, RoleName::Staff);
    NotificationFixture::userWithRole($tenant, RoleName::Coach);
    NotificationFixture::userWithRole($tenant, RoleName::Referee);

    $recipients = app(ResolveNotificationRecipientsAction::class)->execute($tenant->club);

    expect($recipients->pluck('id')->all())->toBe([$tenant->user->id, $staff->id]);
});

it('never returns users of another club', function () {
    $tenant = TenantFixture::create();
    $other = TenantFixture::create(RoleName::Staff);

    $recipients = app(ResolveNotificationRecipientsAction::class)->execute($tenant->club);

    expect($recipients->pluck('id')->all())->toBe([$tenant->user->id])
        ->and($recipients->pluck('id'))->not->toContain($other->user->id);
});
