<?php

namespace Database\Factories;

use App\Domain\Club\Models\Club;
use App\Domain\Membership\Models\MembershipPlan;
use App\Domain\Sport\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipPlan>
 */
class MembershipPlanFactory extends Factory
{
    protected $model = MembershipPlan::class;

    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'sport_id' => Sport::factory(),
            'name' => fake()->unique()->words(2, true),
            'price_cents' => fake()->numberBetween(10, 100) * 1000,
            'currency' => 'EGP',
            'duration_months' => fake()->randomElement([1, 3, 6, 12]),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
