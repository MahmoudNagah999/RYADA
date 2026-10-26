<?php

namespace App\Domain\Club\Actions;

use App\Domain\Club\Enums\ClubStatus;
use App\Domain\Club\Models\Club;

class ActivateClubAction
{
    public function execute(Club $club): Club
    {
        if ($club->isActive()) {
            return $club;
        }

        $club->forceFill([
            'status' => ClubStatus::Active,
            'suspended_at' => null,
        ])->save();

        return $club;
    }
}
