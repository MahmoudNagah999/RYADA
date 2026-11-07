<?php

namespace App\Domain\Training\Policies;

use App\Domain\Auth\Enums\PermissionName;
use App\Domain\Training\Models\TrainingGroup;
use App\Models\User;

class TrainingGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(PermissionName::TrainingGroupsView->value);
    }

    public function view(User $user, TrainingGroup $group): bool
    {
        return $this->sameClub($user, $group)
            && $user->checkPermissionTo(PermissionName::TrainingGroupsView->value);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(PermissionName::TrainingGroupsManage->value);
    }

    public function update(User $user, TrainingGroup $group): bool
    {
        return $this->sameClub($user, $group)
            && $user->checkPermissionTo(PermissionName::TrainingGroupsManage->value);
    }

    public function viewAttendance(User $user, TrainingGroup $group): bool
    {
        return $this->sameClub($user, $group)
            && $user->checkPermissionTo(PermissionName::AttendanceView->value)
            && $this->handlesGroup($user, $group);
    }

    public function recordAttendance(User $user, TrainingGroup $group): bool
    {
        return $this->sameClub($user, $group)
            && $user->checkPermissionTo(PermissionName::AttendanceRecord->value)
            && $this->handlesGroup($user, $group);
    }

    private function sameClub(User $user, TrainingGroup $group): bool
    {
        return $user->club_id === $group->club_id;
    }

    /**
     * Whoever manages groups (Owner, Staff) handles every group of the club;
     * anyone else (a Coach) only the groups they are the coach of.
     */
    private function handlesGroup(User $user, TrainingGroup $group): bool
    {
        return $user->checkPermissionTo(PermissionName::TrainingGroupsManage->value)
            || $group->coach_id === $user->id;
    }
}
