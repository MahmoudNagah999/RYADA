<?php

namespace Tests\Support\Notification;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Subscription\Models\Plan;
use Illuminate\Support\Carbon;
use Tests\Support\Tenancy\TenantFixture;

/**
 * Tenants on a SaaS plan, for the plan-expiry reminder tests. A class keeps these helpers out
 * of the global namespace that Pest helper functions would pollute.
 */
final class PlanExpiryFixture
{
    /**
     * A tenant whose plan ends at the given UTC moment; `null` keeps the end date empty.
     *
     * @param  array<string, mixed>  $attributes  extra `clubs` columns, e.g. `status`, `plan_id`
     */
    public static function tenant(
        ?string $endsAt = null,
        array $attributes = [],
        RoleName $role = RoleName::Owner,
    ): TenantFixture {
        $tenant = TenantFixture::create($role);

        return self::setPlan($tenant, $endsAt, array_merge([
            'plan_id' => Plan::factory()->create()->id,
        ], $attributes));
    }

    /**
     * Renew the plan of an existing tenant: the end date moves, everything else stays.
     */
    public static function renew(TenantFixture $tenant, ?string $endsAt): TenantFixture
    {
        return self::setPlan($tenant, $endsAt);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function setPlan(TenantFixture $tenant, ?string $endsAt, array $attributes = []): TenantFixture
    {
        $ends = $endsAt === null ? null : Carbon::parse($endsAt, 'UTC');

        $tenant->club->forceFill(array_merge([
            'subscription_starts_at' => $ends?->copy()->subYear(),
            'subscription_ends_at' => $ends,
        ], $attributes))->save();

        $tenant->club->refresh();

        return $tenant;
    }
}
