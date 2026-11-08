<?php

namespace Database\Factories;

use App\Domain\Club\Models\Club;
use App\Domain\Membership\Models\MembershipPlan;
use App\Domain\Player\Models\Player;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PlayerSubscription>
 */
class PlayerSubscriptionFactory extends Factory
{
    protected $model = PlayerSubscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = Carbon::today();

        return [
            'club_id' => Club::factory(),
            'player_id' => Player::factory(),
            'membership_plan_id' => MembershipPlan::factory(),
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $startsOn->copy()->addMonthNoOverflow()->toDateString(),
            'price_cents' => 50000,
            'currency' => 'EGP',
            'duration_months' => 1,
            'discount_type' => null,
            'discount_value' => null,
            'discount_cents' => 0,
            'total_cents' => 50000,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'renewed_from_id' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(function (): array {
            $startsOn = Carbon::today();

            return [
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $startsOn->copy()->addMonthNoOverflow()->toDateString(),
                'cancelled_at' => null,
            ];
        });
    }

    public function expired(): static
    {
        return $this->state(function (): array {
            $startsOn = Carbon::today()->subMonths(2);

            return [
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $startsOn->copy()->addMonthNoOverflow()->toDateString(),
                'cancelled_at' => null,
            ];
        });
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'cancelled_at' => Carbon::now(),
        ]);
    }
}
