<?php

namespace App\Domain\Training\Models;

use App\Domain\Player\Models\Player;
use App\Domain\Shared\Tenancy\BelongsToClub;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A player's membership in a training group (tenant-owned).
 *
 * @property int $id
 * @property int $club_id
 * @property int $training_group_id
 * @property int $player_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TrainingGroupPlayer extends Model
{
    use BelongsToClub;

    protected $table = 'training_group_player';

    /** @var list<string> */
    protected $fillable = ['training_group_id', 'player_id'];

    /**
     * @return BelongsTo<TrainingGroup, $this>
     */
    public function trainingGroup(): BelongsTo
    {
        return $this->belongsTo(TrainingGroup::class);
    }

    /**
     * @return BelongsTo<Player, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
