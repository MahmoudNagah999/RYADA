<?php

namespace App\Domain\Audit\Models;

use App\Domain\Club\Models\Club;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as BaseActivity;

/**
 * @property int|null $club_id
 */
class Activity extends BaseActivity
{
    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
