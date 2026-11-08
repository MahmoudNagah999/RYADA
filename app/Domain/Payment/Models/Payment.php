<?php

namespace App\Domain\Payment\Models;

use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Policies\PaymentPolicy;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use App\Domain\Shared\Tenancy\BelongsToClub;
use App\Models\User;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A paid amount for one player subscription (tenant-owned). Payments are immutable:
 * they are voided, never updated or deleted (see ADR-008).
 *
 * @property int $id
 * @property int $club_id
 * @property int $player_subscription_id
 * @property int $receipt_number
 * @property int $amount_cents
 * @property PaymentMethod $method
 * @property Carbon $paid_on
 * @property string|null $reference
 * @property string|null $note
 * @property int|null $recorded_by
 * @property Carbon|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[UseFactory(PaymentFactory::class)]
#[UsePolicy(PaymentPolicy::class)]
class Payment extends Model
{
    use BelongsToClub, HasFactory;

    /**
     * club_id is not mass-assignable: BelongsToClub fills it from CurrentClub.
     *
     * @var list<string>
     */
    protected $fillable = [
        'player_subscription_id',
        'receipt_number',
        'amount_cents',
        'method',
        'paid_on',
        'reference',
        'note',
        'recorded_by',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'receipt_number' => 'integer',
            'amount_cents' => 'integer',
            'method' => PaymentMethod::class,
            'paid_on' => 'date:Y-m-d',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PlayerSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PlayerSubscription::class, 'player_subscription_id');
    }

    /**
     * @return HasMany<PaymentRefund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
