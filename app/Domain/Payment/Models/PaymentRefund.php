<?php

namespace App\Domain\Payment\Models;

use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Shared\Tenancy\BelongsToClub;
use App\Models\User;
use Database\Factories\PaymentRefundFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A refund against a payment (tenant-owned). Refunds are immutable records.
 *
 * @property int $id
 * @property int $club_id
 * @property int $payment_id
 * @property int $refund_number
 * @property int $amount_cents
 * @property PaymentMethod $method
 * @property Carbon $refunded_on
 * @property string $reason
 * @property int|null $recorded_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[UseFactory(PaymentRefundFactory::class)]
class PaymentRefund extends Model
{
    use BelongsToClub, HasFactory;

    /**
     * club_id is not mass-assignable: BelongsToClub fills it from CurrentClub.
     *
     * @var list<string>
     */
    protected $fillable = [
        'payment_id',
        'refund_number',
        'amount_cents',
        'method',
        'refunded_on',
        'reason',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'refund_number' => 'integer',
            'amount_cents' => 'integer',
            'method' => PaymentMethod::class,
            'refunded_on' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
