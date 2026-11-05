<?php

namespace App\Domain\Membership\Models;

use App\Domain\Membership\Policies\MembershipPlanPolicy;
use App\Domain\Shared\Tenancy\BelongsToClub;
use App\Domain\Sport\Models\Sport;
use Database\Factories\MembershipPlanFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A club-defined price plan for one sport (tenant-owned). Not the SaaS `Plan`. See ADR-007.
 *
 * @property int $id
 * @property int $club_id
 * @property int $sport_id
 * @property string $name
 * @property int $price_cents
 * @property string $currency
 * @property int $duration_months
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[UseFactory(MembershipPlanFactory::class)]
#[UsePolicy(MembershipPlanPolicy::class)]
class MembershipPlan extends Model
{
    use BelongsToClub, HasFactory;

    /** @var list<string> */
    protected $fillable = ['sport_id', 'name', 'price_cents', 'currency', 'duration_months', 'is_active'];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'duration_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Sport, $this>
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }
}
