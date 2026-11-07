<?php

namespace App\Http\Requests\Training;

use App\Domain\Training\DTOs\ScheduleSlotData;
use App\Domain\Training\Enums\DayOfWeek;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class TrainingGroupFormRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function sharedRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'coach_id' => ['nullable', 'integer'],
            'schedules' => ['present', 'array', 'max:21'],
            'schedules.*.day_of_week' => ['required', 'integer', Rule::enum(DayOfWeek::class)],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @return list<ScheduleSlotData>
     */
    public function scheduleSlots(): array
    {
        return collect($this->input('schedules', []))
            ->map(fn (array $slot) => new ScheduleSlotData(
                (int) $slot['day_of_week'],
                (string) $slot['start_time'],
                (string) $slot['end_time'],
            ))
            ->values()
            ->all();
    }

    public function coachId(): ?int
    {
        return $this->filled('coach_id') ? $this->integer('coach_id') : null;
    }
}
