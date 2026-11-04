<?php

namespace Database\Factories;

use App\Domain\Club\Models\Club;
use App\Domain\Player\Enums\Gender;
use App\Domain\Player\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    protected $model = Player::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'name' => fake()->name(),
            'national_id' => null,
            'birth_date' => fake()->dateTimeBetween('-40 years', '-6 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(Gender::cases()),
            'phone' => fake()->numerify('010########'),
        ];
    }
}
