<?php

namespace App\Domain\Shared\Tenancy;

use App\Domain\Club\Models\Club;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

trait BelongsToClub
{
    public static function bootBelongsToClub(): void
    {
        static::addGlobalScope(new ClubScope);

        static::creating(function (Model $model) {
            if ($model->getAttribute('club_id') !== null) {
                return;
            }

            $clubId = app(CurrentClub::class)->id();

            if ($clubId === null) {
                throw new LogicException(
                    sprintf('Cannot create %s without a current club.', $model::class)
                );
            }

            $model->setAttribute('club_id', $clubId);
        });
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
