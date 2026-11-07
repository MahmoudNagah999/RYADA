<?php

namespace App\Domain\Training\Actions;

use App\Domain\Training\Models\TrainingGroup;
use Illuminate\Database\Eloquent\Collection;

class ListTrainingGroupsAction
{
    /**
     * Current club only (global scope). No pagination: a club has a handful of groups.
     *
     * @return Collection<int, TrainingGroup>
     */
    public function execute(?int $sportId, ?bool $isActive): Collection
    {
        return TrainingGroup::query()
            ->with(['sport', 'coach', 'schedules'])
            ->when($sportId !== null, fn ($query) => $query->where('sport_id', $sportId))
            ->when($isActive !== null, fn ($query) => $query->where('is_active', $isActive))
            ->orderBy('sport_id')
            ->orderBy('name')
            ->get();
    }
}
