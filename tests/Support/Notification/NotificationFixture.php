<?php

namespace Tests\Support\Notification;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Membership\Models\MembershipPlan;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\PaymentRefund;
use App\Domain\Player\Models\Player;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\Support\Tenancy\TenantFixture;

/**
 * Fixtures shared by the notification tests. A class keeps them out of the global
 * namespace that Pest helper functions would pollute.
 */
final class NotificationFixture
{
    public static function userWithRole(TenantFixture $tenant, RoleName $role): User
    {
        $user = User::factory()->forClub($tenant->club)->create();

        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId($tenant->club->id);

        try {
            $user->assignRole($role->value);
        } finally {
            setPermissionsTeamId($previousTeamId);
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function subscription(TenantFixture $tenant, array $attributes = []): PlayerSubscription
    {
        $plan = MembershipPlan::factory()->create([
            'club_id' => $tenant->club->id,
            'name' => 'Gold Monthly',
        ]);
        $player = Player::factory()->create([
            'club_id' => $tenant->club->id,
            'name' => 'Ali Hassan',
        ]);

        return PlayerSubscription::factory()->create(array_merge([
            'club_id' => $tenant->club->id,
            'player_id' => $player->id,
            'membership_plan_id' => $plan->id,
            'price_cents' => 50000,
            'currency' => 'EGP',
            'total_cents' => 50000,
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-06-05',
            'cancelled_at' => null,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function payment(
        TenantFixture $tenant,
        PlayerSubscription $subscription,
        int $amountCents,
        array $attributes = [],
    ): Payment {
        return app(CurrentClub::class)->runAs($tenant->club, fn (): Payment => Payment::factory()->create(array_merge([
            'club_id' => $tenant->club->id,
            'player_subscription_id' => $subscription->id,
            'amount_cents' => $amountCents,
            'paid_on' => '2026-05-02',
        ], $attributes)));
    }

    public static function refund(TenantFixture $tenant, Payment $payment, int $amountCents): PaymentRefund
    {
        return app(CurrentClub::class)->runAs($tenant->club, fn (): PaymentRefund => PaymentRefund::factory()->create([
            'club_id' => $tenant->club->id,
            'payment_id' => $payment->id,
            'amount_cents' => $amountCents,
            'refunded_on' => '2026-05-03',
        ]));
    }

    /**
     * Insert a notification row directly, so a test controls its code, params and timestamp.
     *
     * @param  array<string, mixed>  $params
     */
    public static function notification(
        User $user,
        string $code,
        array $params = [],
        ?string $dedupKey = null,
    ): DatabaseNotification {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Domain\Notification\Notifications\WelcomeClubNotification',
            'data' => [
                'code' => $code,
                'params' => $params,
                'dedup_key' => $dedupKey,
            ],
        ]);

        return $notification;
    }
}
