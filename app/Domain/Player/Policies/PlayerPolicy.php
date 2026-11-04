<?php

namespace App\Domain\Player\Policies;

use App\Domain\Auth\Enums\PermissionName;
use App\Domain\Player\Models\Player;
use App\Models\User;

class PlayerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(PermissionName::PlayersView->value);
    }

    public function view(User $user, Player $player): bool
    {
        return $this->sameClub($user, $player)
            && $user->checkPermissionTo(PermissionName::PlayersView->value);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(PermissionName::PlayersManage->value);
    }

    public function update(User $user, Player $player): bool
    {
        return $this->sameClub($user, $player)
            && $user->checkPermissionTo(PermissionName::PlayersManage->value);
    }

    public function delete(User $user, Player $player): bool
    {
        return $this->sameClub($user, $player)
            && $user->checkPermissionTo(PermissionName::PlayersManage->value);
    }

    private function sameClub(User $user, Player $player): bool
    {
        return $user->club_id === $player->club_id;
    }
}
