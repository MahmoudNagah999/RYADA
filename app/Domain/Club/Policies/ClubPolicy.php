<?php

namespace App\Domain\Club\Policies;

use App\Domain\Auth\Enums\PermissionName;
use App\Domain\Club\Models\Club;
use App\Models\User;

class ClubPolicy
{
    public function view(User $user, Club $club): bool
    {
        return $this->sameClub($user, $club)
            && $user->checkPermissionTo(PermissionName::ClubView->value);
    }

    public function update(User $user, Club $club): bool
    {
        return $this->sameClub($user, $club)
            && $user->checkPermissionTo(PermissionName::ClubUpdate->value);
    }

    private function sameClub(User $user, Club $club): bool
    {
        return $user->club_id === $club->id;
    }
}
