<?php

namespace App\Domain\Sport\Models;

use App\Domain\Shared\Tenancy\BelongsToClub;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A sport offered by a club (tenant-owned). See ADR-007.
 *
 * @property int $id
 * @property int $club_id
 * @property int $sport_id
 * @property Carbon $created_at
 */
class ClubSport extends Model
{
    use BelongsToClub;

    protected $table = 'club_sport';

    /** @var list<string> */
    protected $fillable = ['sport_id'];

    /**
     * @return BelongsTo<Sport, $this>
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }
}
