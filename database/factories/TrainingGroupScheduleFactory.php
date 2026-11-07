<?php

namespace Database\Factories;

use App\Domain\Club\Models\Club;
use App\Domain\Training\Enums\DayOfWeek;
use App\Domain\Training\Models\TrainingGroup;
use App\Domain\Training\Models\TrainingGroupSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingGroupSchedule>
 */
class TrainingGroupScheduleFactory extends Factory
{
    protected $model = TrainingGroupSchedule::class;

    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'training_group_id' => TrainingGroup::factory(),
            'day_of_week' => DayOfWeek::Monday,
            'start_time' => '17:00:00',
            'end_time' => '18:30:00',
        ];
    }
}
