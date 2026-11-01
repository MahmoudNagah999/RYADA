<?php

namespace App\Domain\Staff\Policies;

use App\Domain\Auth\Enums\PermissionName;
use App\Domain\Staff\Models\StaffInvitation;
use App\Models\User;

class StaffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(PermissionName::StaffView->value);
    }

    public function view(User $user, User $staffMember): bool
    {
        return $this->sameClub($user, $staffMember->club_id)
            && $user->checkPermissionTo(PermissionName::StaffView->value);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(PermissionName::StaffManage->value);
    }

    public function manage(User $user, User|StaffInvitation $subject): bool
    {
        return $this->sameClub($user, $subject->club_id)
            && $user->checkPermissionTo(PermissionName::StaffManage->value);
    }

    private function sameClub(User $user, ?int $clubId): bool
    {
        return $clubId !== null && $user->club_id === $clubId;
    }
}
