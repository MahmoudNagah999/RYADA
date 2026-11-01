<?php

namespace Database\Factories;

use App\Domain\Club\Models\Club;
use App\Domain\Staff\Models\StaffInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StaffInvitation>
 */
class StaffInvitationFactory extends Factory
{
    protected $model = StaffInvitation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => 'coach',
            'token_hash' => hash('sha256', Str::random(64)),
            'invited_by' => null,
            'expires_at' => now()->addDays((int) config('app.invitation_expiry_days', 7)),
            'accepted_at' => null,
            'revoked_at' => null,
        ];
    }
}
