<?php

namespace Database\Factories;

use App\Domain\Club\Models\Club;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Models\Payment;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'player_subscription_id' => PlayerSubscription::factory(),
            'receipt_number' => fake()->unique()->numberBetween(1, 1000000),
            'amount_cents' => 50000,
            'method' => PaymentMethod::Cash,
            'paid_on' => Carbon::today()->toDateString(),
            'reference' => null,
            'note' => null,
            'recorded_by' => null,
            'voided_at' => null,
            'voided_by' => null,
            'void_reason' => null,
        ];
    }

    public function voided(): static
    {
        return $this->state(fn (): array => [
            'voided_at' => Carbon::now(),
            'void_reason' => 'Voided in test',
        ]);
    }
}
