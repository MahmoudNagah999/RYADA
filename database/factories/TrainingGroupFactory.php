<?php

namespace Database\Factories;

use App\Domain\Club\Models\Club;
use App\Domain\Sport\Models\Sport;
use App\Domain\Training\Models\TrainingGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingGroup>
 */
class TrainingGroupFactory extends Factory
{
    protected $model = TrainingGroup::class;

    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'sport_id' => Sport::factory(),
            'coach_id' => null,
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
