<?php

namespace App\Domain\Shared\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ClubScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $clubId = app(CurrentClub::class)->id();

        if ($clubId === null) {
            // Fail closed: no tenant context means no rows.
            $builder->whereRaw('0 = 1');

            return;
        }

        $builder->where($model->qualifyColumn('club_id'), $clubId);
    }
}
