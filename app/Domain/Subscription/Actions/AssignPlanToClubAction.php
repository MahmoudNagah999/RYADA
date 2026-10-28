<?php

namespace App\Domain\Subscription\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Club\Models\Club;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Subscription\Models\Plan;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AssignPlanToClubAction
{
    public function __construct(private readonly RecordActivityAction $recordActivity) {}

    public function execute(Club $club, Plan $plan, ?CarbonInterface $startsAt = null): Club
    {
        if (! $plan->is_active) {
            throw new BusinessRuleException('plan.inactive', [], 422);
        }

        $starts = ($startsAt ?? now())->toImmutable();
        $ends = $starts->addMonthsNoOverflow($plan->duration_months);
        $previousPlanId = $club->plan_id;

        DB::transaction(function () use ($club, $plan, $starts, $ends, $previousPlanId): void {
            $club->plan()->associate($plan);
            $club->forceFill([
                'subscription_starts_at' => $starts,
                'subscription_ends_at' => $ends,
            ])->save();

            $this->recordActivity->execute(
                ActivityEvent::ClubPlanAssigned,
                $club,
                [
                    'plan_id' => $plan->getKey(),
                    'plan_slug' => $plan->slug,
                    'previous_plan_id' => $previousPlanId,
                    'starts_at' => $starts->toIso8601String(),
                    'ends_at' => $ends->toIso8601String(),
                ],
            );
        });

        return $club;
    }
}
