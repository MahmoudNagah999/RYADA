<?php

namespace App\Domain\Training\Models;

use App\Domain\Player\Models\Player;
use App\Domain\Shared\Tenancy\BelongsToClub;
use App\Domain\Sport\Models\Sport;
use App\Domain\Training\Policies\TrainingGroupPolicy;
use App\Models\User;
use Database\Factories\TrainingGroupFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A club's training group for one sport (tenant-owned). See ADR-007.
 *
 * @property int $id
 * @property int $club_id
 * @property int $sport_id
 * @property int|null $coach_id
 * @property string $name
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[UseFactory(TrainingGroupFactory::class)]
#[UsePolicy(TrainingGroupPolicy::class)]
class TrainingGroup extends Model
{
    use BelongsToClub, HasFactory;

    /** @var list<string> */
    protected $fillable = ['sport_id', 'coach_id', 'name', 'is_active'];

    protected function casts(): array
    {
        return [
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    /**
     * @return HasMany<TrainingGroupSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(TrainingGroupSchedule::class)
            ->orderBy('day_of_week')
            ->orderBy('start_time');
    }

    /**
     * @return BelongsToMany<Player, $this>
     */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'training_group_player')
            ->withTimestamps();
    }
}
