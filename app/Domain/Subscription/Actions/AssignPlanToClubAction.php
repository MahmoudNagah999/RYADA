<?php

namespace App\Domain\Subscription\Actions;

use App\Domain\Club\Models\Club;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Subscription\Models\Plan;
use Carbon\CarbonInterface;

class AssignPlanToClubAction
{
    public function execute(Club $club, Plan $plan, ?CarbonInterface $startsAt = null): Club
    {
        if (! $plan->is_active) {
            throw new BusinessRuleException('plan.inactive', [], 422);
        }

        $starts = ($startsAt ?? now())->toImmutable();

        $club->plan()->associate($plan);
        $club->forceFill([
            'subscription_starts_at' => $starts,
            'subscription_ends_at' => $starts->addMonthsNoOverflow($plan->duration_months),
        ])->save();

        return $club;
    }
}
