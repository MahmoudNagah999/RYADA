<?php

namespace Database\Factories;

use App\Domain\Subscription\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => str($name)->slug()->value(),
            'name' => ['en' => $name, 'ar' => $name],
            'max_players' => 50,
            'max_coaches' => 5,
            'price_cents' => 50000,
            'currency' => 'EGP',
            'duration_months' => 12,
            'is_active' => true,
        ];
    }

    public function unlimited(): static
    {
        return $this->state(fn () => [
            'max_players' => null,
            'max_coaches' => null,
        ]);
    }
}
