<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Membership\DTOs\UpdateMembershipPlanData;
use App\Domain\Membership\Models\MembershipPlan;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class UpdateMembershipPlanAction
{
    public function __construct(private readonly RecordActivityAction $recordActivity) {}

    /**
     * The sport never changes. Existing subscriptions snapshot price and duration (ADR-007),
     * so editing a plan does not touch them. A save with no changes is not logged.
     */
    public function execute(MembershipPlan $plan, UpdateMembershipPlanData $data): MembershipPlan
    {
        return DB::transaction(function () use ($plan, $data): MembershipPlan {
            $after = [
                'name' => $data->name,
                'price_cents' => $data->priceCents,
                'currency' => $data->currency,
                'duration_months' => $data->durationMonths,
                'is_active' => $data->isActive,
            ];

            $changes = [];
            foreach ($after as $field => $to) {
                $from = $plan->getAttribute($field);

                if ($from !== $to) {
                    $changes[$field] = ['from' => $from, 'to' => $to];
                }
            }

            if ($changes === []) {
                return $plan;
            }

            if (isset($changes['name']) && MembershipPlan::query()
                ->where('sport_id', $plan->sport_id)
                ->where('name', $data->name)
                ->whereKeyNot($plan->id)
                ->exists()) {
                throw new BusinessRuleException('membership_plan.name_taken', [], 422);
            }

            try {
                $plan->fill($after)->save();
            } catch (UniqueConstraintViolationException) {
                throw new BusinessRuleException('membership_plan.name_taken', [], 422);
            }

            $this->recordActivity->execute(
                ActivityEvent::MembershipPlanUpdated,
                $plan,
                ['plan_id' => $plan->id, 'changes' => $changes],
                null,
                null,
            );

            return $plan;
        });
    }
}
