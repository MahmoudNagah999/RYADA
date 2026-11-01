<?php

namespace App\Domain\Club\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Club\Models\Club;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RemoveClubLogoAction
{
    public function __construct(private readonly RecordActivityAction $recordActivity) {}

    public function execute(Club $club): Club
    {
        $oldPath = $club->logo_path;

        // Idempotent no-op: no logo, nothing to do, nothing logged.
        if ($oldPath === null) {
            return $club;
        }

        DB::transaction(function () use ($club) {
            $club->logo_path = null;
            $club->save();

            $this->recordActivity->execute(
                event: ActivityEvent::ClubLogoRemoved,
                subject: $club,
                properties: [],
            );
        });

        Storage::disk(config('filesystems.club_logo_disk'))->delete($oldPath);

        return $club;
    }
}
