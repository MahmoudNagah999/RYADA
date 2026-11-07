<?php

namespace App\Domain\Training\Models;

use App\Domain\Player\Models\Player;
use App\Domain\Shared\Tenancy\BelongsToClub;
use App\Domain\Training\Enums\AttendanceStatus;
use Database\Factories\TrainingAttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One player's attendance at one training date of a group (tenant-owned).
 *
 * @property int $id
 * @property int $club_id
 * @property int $training_group_id
 * @property int $player_id
 * @property Carbon $date
 * @property AttendanceStatus $status
 * @property int|null $recorded_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[UseFactory(TrainingAttendanceFactory::class)]
class TrainingAttendance extends Model
{
    use BelongsToClub, HasFactory;

    /** @var list<string> */
    protected $fillable = ['training_group_id', 'player_id', 'date', 'status', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'status' => AttendanceStatus::class,
        ];
    }

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
