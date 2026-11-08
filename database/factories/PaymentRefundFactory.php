<?php

namespace Database\Factories;

use App\Domain\Club\Models\Club;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\PaymentRefund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PaymentRefund>
 */
class PaymentRefundFactory extends Factory
{
    protected $model = PaymentRefund::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'payment_id' => Payment::factory(),
            'refund_number' => fake()->unique()->numberBetween(1, 1000000),
            'amount_cents' => 10000,
            'method' => PaymentMethod::Cash,
            'refunded_on' => Carbon::today()->toDateString(),
            'reason' => 'Refunded in test',
            'recorded_by' => null,
        ];
    }
}
