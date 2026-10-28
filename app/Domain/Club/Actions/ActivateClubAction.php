<?php

namespace App\Domain\Club\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Club\Enums\ClubStatus;
use App\Domain\Club\Models\Club;
use Illuminate\Support\Facades\DB;

class ActivateClubAction
{
    public function __construct(private readonly RecordActivityAction $recordActivity) {}

    public function execute(Club $club): Club
    {
        if ($club->isActive()) {
            return $club;
        }

        DB::transaction(function () use ($club): void {
            $club->forceFill([
                'status' => ClubStatus::Active,
                'suspended_at' => null,
            ])->save();

            $this->recordActivity->execute(
                ActivityEvent::ClubActivated,
                $club,
                ['status' => ['from' => ClubStatus::Suspended->value, 'to' => ClubStatus::Active->value]],
            );
        });

        return $club;
    }
}
