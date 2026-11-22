<?php

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Notification\Actions\LoadExistingDedupKeysAction;
use App\Domain\Notification\Actions\NotifyDueSubscriptionsAction;
use App\Domain\Notification\Support\ExistingDedupKeys;
use App\Domain\Notification\Support\NotificationSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Notification\NotificationFixture;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function nbdFingerprint(int $recipientId, string $dedupKey): string
{
    return ExistingDedupKeys::fingerprint($recipientId, $dedupKey);
}

it('returns nothing for an empty key list or an empty recipient list', function (): void {
    $tenant = TenantFixture::create();
    $action = app(LoadExistingDedupKeysAction::class);

    expect($action->execute([], ['a-key']))->toBe([])
        ->and($action->execute([$tenant->user->id], []))->toBe([])
        ->and($action->execute([], []))->toBe([]);
});

it('returns only the keys held by the requested recipients', function (): void {
    $tenant = TenantFixture::create();
    $staff = NotificationFixture::userWithRole($tenant, RoleName::Staff);

    NotificationFixture::notification($tenant->user, 'subscription.unpaid', [], 'key-a');

    $fingerprints = app(LoadExistingDedupKeysAction::class)->execute(
        [$tenant->user->id, $staff->id],
        ['key-a', 'key-b'],
    );

    // key-a belongs to the owner only: it must never be attributed to the staff member.
    expect($fingerprints)->toBe([nbdFingerprint((int) $tenant->user->id, 'key-a')]);
});

it('counts read and unread notifications alike', function (): void {
    $tenant = TenantFixture::create();

    NotificationFixture::notification($tenant->user, 'subscription.unpaid', [], 'unread-key');
    $read = NotificationFixture::notification($tenant->user, 'subscription.unpaid', [], 'read-key');
    $read->markAsRead();

    $fingerprints = app(LoadExistingDedupKeysAction::class)->execute(
        [$tenant->user->id],
        ['unread-key', 'read-key'],
    );

    expect($fingerprints)->toEqualCanonicalizing([
        nbdFingerprint((int) $tenant->user->id, 'unread-key'),
        nbdFingerprint((int) $tenant->user->id, 'read-key'),
    ]);
});

it('never returns a key held by a user of another club', function (): void {
    $thisClub = TenantFixture::create();
    $otherClub = TenantFixture::create();

    NotificationFixture::notification($otherClub->user, 'subscription.unpaid', [], 'shared-key');

    $fingerprints = app(LoadExistingDedupKeysAction::class)->execute(
        [$thisClub->user->id],
        ['shared-key'],
    );

    expect($fingerprints)->toBe([]);
});

it('does not let another club notification suppress this club run', function (): void {
    $thisClub = TenantFixture::create();
    $otherClub = TenantFixture::create();

    $subscription = NotificationFixture::subscription($thisClub);
    $key = NotificationSchedule::expiringDedupKey($subscription->id, '2026-06-05');

    // Same key string, but on the owner of another club: it must not silence this club.
    NotificationFixture::notification($otherClub->user, 'subscription.expiring', [], $key);

    app(NotifyDueSubscriptionsAction::class)->execute($thisClub->club);

    expect($thisClub->user->notifications()->where('data->dedup_key', $key)->count())->toBe(1)
        ->and($otherClub->user->notifications()->count())->toBe(1);
});

it('keeps one fingerprint when the same key is added twice in one run', function (): void {
    $keys = new ExistingDedupKeys;

    $keys->add(7, 'same-key');
    $keys->add(7, 'same-key');

    expect($keys->contains(7, 'same-key'))->toBeTrue()
        ->and($keys->contains(8, 'same-key'))->toBeFalse()
        ->and($keys->all())->toHaveCount(1);
});
