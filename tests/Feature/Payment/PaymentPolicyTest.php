<?php

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Membership\Models\MembershipPlan;
use App\Domain\Payment\Models\Payment;
use App\Domain\Player\Models\Player;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

/**
 * @return array{0: TenantFixture, 1: Payment}
 */
function payPolicyContext(RoleName $role, bool $ownClub = true): array
{
    $tenant = TenantFixture::create($role);
    $other = TenantFixture::create();

    $clubId = ($ownClub ? $tenant : $other)->club->id;

    $plan = MembershipPlan::factory()->create(['club_id' => $clubId]);
    $player = Player::factory()->create(['club_id' => $clubId]);
    $subscription = PlayerSubscription::factory()->create([
        'club_id' => $clubId,
        'player_id' => $player->id,
        'membership_plan_id' => $plan->id,
    ]);
    $payment = Payment::factory()->create([
        'club_id' => $clubId,
        'player_subscription_id' => $subscription->id,
    ]);

    setPermissionsTeamId($tenant->club->id);

    return [$tenant, $payment];
}

it('lets the owner do everything with payments', function () {
    [$tenant, $payment] = payPolicyContext(RoleName::Owner);

    expect(Gate::forUser($tenant->user)->allows('viewAny', Payment::class))->toBeTrue()
        ->and(Gate::forUser($tenant->user)->allows('view', $payment))->toBeTrue()
        ->and(Gate::forUser($tenant->user)->allows('create', Payment::class))->toBeTrue()
        ->and(Gate::forUser($tenant->user)->allows('void', $payment))->toBeTrue()
        ->and(Gate::forUser($tenant->user)->allows('refund', $payment))->toBeTrue();
});

it('lets staff view, record and void but not refund', function () {
    [$tenant, $payment] = payPolicyContext(RoleName::Staff);

    expect(Gate::forUser($tenant->user)->allows('viewAny', Payment::class))->toBeTrue()
        ->and(Gate::forUser($tenant->user)->allows('view', $payment))->toBeTrue()
        ->and(Gate::forUser($tenant->user)->allows('create', Payment::class))->toBeTrue()
        ->and(Gate::forUser($tenant->user)->allows('void', $payment))->toBeTrue()
        ->and(Gate::forUser($tenant->user)->allows('refund', $payment))->toBeFalse();
});

it('gives a coach no access to payments', function () {
    [$tenant, $payment] = payPolicyContext(RoleName::Coach);

    expect(Gate::forUser($tenant->user)->allows('viewAny', Payment::class))->toBeFalse()
        ->and(Gate::forUser($tenant->user)->allows('view', $payment))->toBeFalse()
        ->and(Gate::forUser($tenant->user)->allows('create', Payment::class))->toBeFalse()
        ->and(Gate::forUser($tenant->user)->allows('void', $payment))->toBeFalse()
        ->and(Gate::forUser($tenant->user)->allows('refund', $payment))->toBeFalse();
});

it('gives a referee no access to payments', function () {
    [$tenant, $payment] = payPolicyContext(RoleName::Referee);

    expect(Gate::forUser($tenant->user)->allows('viewAny', Payment::class))->toBeFalse()
        ->and(Gate::forUser($tenant->user)->allows('view', $payment))->toBeFalse()
        ->and(Gate::forUser($tenant->user)->allows('refund', $payment))->toBeFalse();
});

it('denies a payment of another club even for the owner', function () {
    [$tenant, $payment] = payPolicyContext(RoleName::Owner, ownClub: false);

    expect(Gate::forUser($tenant->user)->allows('view', $payment))->toBeFalse()
        ->and(Gate::forUser($tenant->user)->allows('void', $payment))->toBeFalse()
        ->and(Gate::forUser($tenant->user)->allows('refund', $payment))->toBeFalse()
        // Class-level abilities are club-less; the controller scopes the record.
        ->and(Gate::forUser($tenant->user)->allows('viewAny', Payment::class))->toBeTrue();
});
