<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Collection;

class ListMembershipPlansAction
{
    /**
     * Runs in the current club context (ClubScope limits the rows).
     *
     * @return Collection<int, MembershipPlan>
     */
    public function execute(?int $sportId = null, ?bool $isActive = null): Collection
    {
        return MembershipPlan::query()
            ->with('sport')
            ->when($sportId !== null, fn ($query) => $query->where('sport_id', $sportId))
            ->when($isActive !== null, fn ($query) => $query->where('is_active', $isActive))
            ->orderBy('sport_id')
            ->orderBy('name')
            ->get();
    }
}
