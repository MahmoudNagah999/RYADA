<?php

namespace App\Domain\Subscription\Actions;

use App\Domain\Club\Models\Club;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Subscription\Enums\PlanLimit;

class EnsureWithinPlanLimitAction
{
    /**
     * Throws when adding $adding more records would go over the club's plan limit.
     * A club without a plan, or a plan without that limit, is unlimited.
     *
     * @throws BusinessRuleException
     */
    public function execute(Club $club, PlanLimit $limit, int $currentCount, int $adding = 1): void
    {
        $max = $club->plan?->limitFor($limit);

        if ($max === null) {
            return;
        }

        if ($currentCount + $adding > $max) {
            throw new BusinessRuleException($limit->errorCode(), ['max' => $max], 403);
        }
    }
}
