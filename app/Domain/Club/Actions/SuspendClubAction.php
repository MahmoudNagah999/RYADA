<?php

namespace App\Domain\Club\Actions;

use App\Domain\Club\Enums\ClubStatus;
use App\Domain\Club\Models\Club;

class SuspendClubAction
{
    public function execute(Club $club): Club
    {
        if ($club->isSuspended()) {
            return $club;
        }

        $club->forceFill([
            'status' => ClubStatus::Suspended,
            'suspended_at' => now(),
        ])->save();

        return $club;
    }
}
