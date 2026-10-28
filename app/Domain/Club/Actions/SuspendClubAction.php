<?php

namespace App\Domain\Club\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Club\Enums\ClubStatus;
use App\Domain\Club\Models\Club;
use Illuminate\Support\Facades\DB;

class SuspendClubAction
{
    public function __construct(private readonly RecordActivityAction $recordActivity) {}

    public function execute(Club $club): Club
    {
        if ($club->isSuspended()) {
            return $club;
        }

        DB::transaction(function () use ($club): void {
            $club->forceFill([
                'status' => ClubStatus::Suspended,
                'suspended_at' => now(),
            ])->save();

            $this->recordActivity->execute(
                ActivityEvent::ClubSuspended,
                $club,
                ['status' => ['from' => ClubStatus::Active->value, 'to' => ClubStatus::Suspended->value]],
            );
        });

        return $club;
    }
}
