<?php

namespace App\Domain\Membership\Policies;

use App\Domain\Auth\Enums\PermissionName;
use App\Domain\Membership\Models\MembershipPlan;
use App\Models\User;

class MembershipPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(PermissionName::MembershipPlansView->value);
    }

    public function view(User $user, MembershipPlan $plan): bool
    {
        return $this->sameClub($user, $plan)
            && $user->checkPermissionTo(PermissionName::MembershipPlansView->value);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(PermissionName::MembershipPlansManage->value);
    }

    public function update(User $user, MembershipPlan $plan): bool
    {
        return $this->sameClub($user, $plan)
            && $user->checkPermissionTo(PermissionName::MembershipPlansManage->value);
    }

    private function sameClub(User $user, MembershipPlan $plan): bool
    {
        return $user->club_id === $plan->club_id;
    }
}
