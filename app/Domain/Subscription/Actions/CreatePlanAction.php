<?php

namespace App\Domain\Subscription\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Subscription\DTOs\PlanData;
use App\Domain\Subscription\Models\Plan;
use Illuminate\Support\Facades\DB;

class CreatePlanAction
{
    public function __construct(private readonly RecordActivityAction $recordActivity) {}

    public function execute(PlanData $data): Plan
    {
        return DB::transaction(function () use ($data): Plan {
            $plan = Plan::create($data->toAttributes());

            $this->recordActivity->execute(
                ActivityEvent::PlanCreated,
                $plan,
                [
                    'slug' => $plan->slug,
                    'price_cents' => $plan->price_cents,
                    'duration_months' => $plan->duration_months,
                    'max_players' => $plan->max_players,
                    'max_coaches' => $plan->max_coaches,
                    'is_active' => $plan->is_active,
                ],
            );

            return $plan;
        });
    }
}
