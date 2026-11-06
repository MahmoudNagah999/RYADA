<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Membership\DTOs\CreateMembershipPlanData;
use App\Domain\Membership\Models\MembershipPlan;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Sport\Models\ClubSport;
use App\Domain\Sport\Models\Sport;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CreateMembershipPlanAction
{
    public function __construct(private readonly RecordActivityAction $recordActivity) {}

    /**
     * Runs in the current club context. The sport must be offered by the club and active.
     */
    public function execute(CreateMembershipPlanData $data): MembershipPlan
    {
        return DB::transaction(function () use ($data): MembershipPlan {
            $sport = Sport::query()->findOrFail($data->sportId);

            if (! ClubSport::query()->where('sport_id', $sport->id)->exists()) {
                throw new BusinessRuleException('membership_plan.sport_not_offered', [], 422);
            }

            if (! $sport->is_active) {
                throw new BusinessRuleException('sport.inactive', [], 422);
            }

            if (MembershipPlan::query()
                ->where('sport_id', $sport->id)
                ->where('name', $data->name)
                ->exists()) {
                throw new BusinessRuleException('membership_plan.name_taken', [], 422);
            }

            try {
                $plan = MembershipPlan::query()->create([
                    'sport_id' => $sport->id,
                    'name' => $data->name,
                    'price_cents' => $data->priceCents,
                    'currency' => $data->currency,
                    'duration_months' => $data->durationMonths,
                    'is_active' => $data->isActive,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Two concurrent requests with the same name: the unique index is the backstop.
                throw new BusinessRuleException('membership_plan.name_taken', [], 422);
            }

            $this->recordActivity->execute(
                ActivityEvent::MembershipPlanCreated,
                $plan,
                [
                    'plan_id' => $plan->id,
                    'sport' => $sport->slug,
                    'name' => $plan->name,
                    'price_cents' => $plan->price_cents,
                    'currency' => $plan->currency,
                    'duration_months' => $plan->duration_months,
                ],
                null,
                null,
            );

            return $plan;
        });
    }
}
