<?php

namespace App\Domain\Sport\Models;

use App\Domain\Player\Models\Player;
use App\Domain\Shared\Tenancy\BelongsToClub;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A player enrolled in a sport (tenant-owned). See ADR-007.
 *
 * @property int $id
 * @property int $club_id
 * @property int $player_id
 * @property int $sport_id
 * @property Carbon $created_at
 */
class PlayerSport extends Model
{
    use BelongsToClub;

    protected $table = 'player_sport';

    /** @var list<string> */
    protected $fillable = ['player_id', 'sport_id'];

    /**
     * @return BelongsTo<Sport, $this>
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * @return BelongsTo<Player, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
