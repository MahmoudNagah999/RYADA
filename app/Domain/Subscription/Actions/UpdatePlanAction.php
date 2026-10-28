<?php

namespace App\Domain\Subscription\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Subscription\DTOs\PlanData;
use App\Domain\Subscription\Models\Plan;
use Illuminate\Support\Facades\DB;

class UpdatePlanAction
{
    public function __construct(private readonly RecordActivityAction $recordActivity) {}

    public function execute(Plan $plan, PlanData $data): Plan
    {
        return DB::transaction(function () use ($plan, $data): Plan {
            $plan->fill($data->toAttributes());

            $changes = $this->changes($plan);

            if ($changes === []) {
                return $plan;
            }

            $plan->save();

            $this->recordActivity->execute(
                ActivityEvent::PlanUpdated,
                $plan,
                ['changes' => $changes],
            );

            return $plan;
        });
    }

    /**
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function changes(Plan $plan): array
    {
        $changes = [];

        foreach ($plan->getDirty() as $field => $new) {
            $old = $plan->getOriginal($field);

            // Translatable columns are stored as JSON: log them as readable arrays.
            if ($field === 'name') {
                $old = is_string($old) ? json_decode($old, true) : $old;
                $new = is_string($new) ? json_decode($new, true) : $new;
            }

            $changes[$field] = ['from' => $old, 'to' => $new];
        }

        return $changes;
    }
}
