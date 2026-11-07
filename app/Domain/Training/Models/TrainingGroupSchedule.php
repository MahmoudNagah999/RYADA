<?php

namespace App\Domain\Training\Models;

use App\Domain\Shared\Tenancy\BelongsToClub;
use App\Domain\Training\Enums\DayOfWeek;
use Database\Factories\TrainingGroupScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One weekly training slot of a group (tenant-owned). Times are stored as `H:i:s` strings.
 *
 * @property int $id
 * @property int $club_id
 * @property int $training_group_id
 * @property DayOfWeek $day_of_week
 * @property string $start_time
 * @property string $end_time
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[UseFactory(TrainingGroupScheduleFactory::class)]
class TrainingGroupSchedule extends Model
{
    use BelongsToClub, HasFactory;

    /** @var list<string> */
    protected $fillable = ['training_group_id', 'day_of_week', 'start_time', 'end_time'];

    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
        ];
    }

    /**
     * @return BelongsTo<TrainingGroup, $this>
     */
    public function trainingGroup(): BelongsTo
    {
        return $this->belongsTo(TrainingGroup::class);
    }
}
