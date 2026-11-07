<?php

namespace Database\Factories;

use App\Domain\Club\Models\Club;
use App\Domain\Player\Models\Player;
use App\Domain\Training\Enums\AttendanceStatus;
use App\Domain\Training\Models\TrainingAttendance;
use App\Domain\Training\Models\TrainingGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingAttendance>
 */
class TrainingAttendanceFactory extends Factory
{
    protected $model = TrainingAttendance::class;

    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'training_group_id' => TrainingGroup::factory(),
            'player_id' => Player::factory(),
            'date' => now()->toDateString(),
            'status' => AttendanceStatus::Present,
            'recorded_by' => null,
        ];
    }
}
