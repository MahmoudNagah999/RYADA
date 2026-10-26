<?php

namespace App\Domain\Club\Models;

use App\Domain\Club\Enums\ClubStatus;
use App\Domain\Club\Policies\ClubPolicy;
use App\Domain\Subscription\Models\Plan;
use App\Models\User;
use Database\Factories\ClubFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property ClubStatus $status
 * @property Carbon|null $suspended_at
 * @property int|null $plan_id
 * @property Carbon|null $subscription_starts_at
 * @property Carbon|null $subscription_ends_at
 */
#[UseFactory(ClubFactory::class)]
#[UsePolicy(ClubPolicy::class)]
class Club extends Model
{
    /** @use HasFactory<ClubFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'status' => ClubStatus::class,
            'suspended_at' => 'datetime',
            'subscription_starts_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        return $this->status === ClubStatus::Active;
    }

    public function isSuspended(): bool
    {
        return $this->status === ClubStatus::Suspended;
    }

    public function hasExpiredSubscription(): bool
    {
        return $this->subscription_ends_at !== null
            && $this->subscription_ends_at->isPast();
    }
}
