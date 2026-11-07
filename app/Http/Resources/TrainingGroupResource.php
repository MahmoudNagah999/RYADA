<?php

namespace App\Http\Resources;

use App\Domain\Training\Models\TrainingGroup;
use App\Domain\Training\Models\TrainingGroupSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrainingGroup
 */
class TrainingGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sport_id' => $this->sport_id,
            'sport' => $this->whenLoaded('sport', fn () => [
                'slug' => $this->sport->slug,
                'name' => $this->sport->name,
            ]),
            'coach_id' => $this->coach_id,
            'coach' => $this->whenLoaded('coach', fn () => $this->coach === null ? null : [
                'id' => $this->coach->id,
                'name' => $this->coach->name,
            ]),
            'name' => $this->name,
            'is_active' => $this->is_active,
            'schedules' => $this->whenLoaded('schedules', fn () => $this->schedules
                ->map(fn (TrainingGroupSchedule $slot) => [
                    'id' => $slot->id,
                    'day_of_week' => $slot->day_of_week->value,
                    'start_time' => substr($slot->start_time, 0, 5),
                    'end_time' => substr($slot->end_time, 0, 5),
                ])
                ->values()
                ->all()),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
