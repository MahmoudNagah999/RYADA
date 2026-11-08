<?php

namespace App\Domain\PlayerSubscription\Models;

use App\Domain\Membership\Enums\DiscountType;
use App\Domain\Membership\Models\MembershipPlan;
use App\Domain\Player\Models\Player;
use App\Domain\PlayerSubscription\Enums\SubscriptionStatus;
use App\Domain\PlayerSubscription\Policies\PlayerSubscriptionPolicy;
use App\Domain\Shared\Tenancy\BelongsToClub;
use Database\Factories\PlayerSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A player's paid period on a membership plan (tenant-owned). It snapshots the plan price,
 * currency and duration at subscription time. See ADR-007.
 *
 * @property int $id
 * @property int $club_id
 * @property int $player_id
 * @property int $membership_plan_id
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property int $price_cents
 * @property string $currency
 * @property int $duration_months
 * @property DiscountType|null $discount_type
 * @property int|null $discount_value
 * @property int $discount_cents
 * @property int $total_cents
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property int|null $renewed_from_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static Builder<PlayerSubscription> status(SubscriptionStatus $status, string $today)
 */
#[UseFactory(PlayerSubscriptionFactory::class)]
#[UsePolicy(PlayerSubscriptionPolicy::class)]
class PlayerSubscription extends Model
{
    use BelongsToClub, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'player_id',
        'membership_plan_id',
        'starts_on',
        'ends_on',
        'price_cents',
        'currency',
        'duration_months',
        'discount_type',
        'discount_value',
        'discount_cents',
        'total_cents',
        'cancelled_at',
        'cancelled_by',
        'renewed_from_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'price_cents' => 'integer',
            'duration_months' => 'integer',
            'discount_type' => DiscountType::class,
            'discount_value' => 'integer',
            'discount_cents' => 'integer',
            'total_cents' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Player, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * @return BelongsTo<MembershipPlan, $this>
     */
    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    /**
     * @return BelongsTo<PlayerSubscription, $this>
     */
    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_id');
    }

    /**
     * Pure status for a given `Y-m-d` "today" (ends_on is exclusive).
     */
    public function statusOn(string $today): SubscriptionStatus
    {
        return SubscriptionStatus::forDate(
            $this->cancelled_at?->toDateString(),
            $this->ends_on->toDateString(),
            $today,
        );
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Filter by computed status for a given `Y-m-d` "today".
     *
     * @param  Builder<PlayerSubscription>  $query
     */
    public function scopeStatus(Builder $query, SubscriptionStatus $status, string $today): void
    {
        match ($status) {
            SubscriptionStatus::Cancelled => $query->whereNotNull('cancelled_at'),
            SubscriptionStatus::Expired => $query->whereNull('cancelled_at')->where('ends_on', '<=', $today),
            SubscriptionStatus::Active => $query->whereNull('cancelled_at')->where('ends_on', '>', $today),
        };
    }
}
