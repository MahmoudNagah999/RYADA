<?php

namespace App\Domain\Training\Actions;

use App\Domain\Training\DTOs\AttendanceRowData;
use App\Domain\Training\Models\TrainingAttendance;
use App\Domain\Training\Models\TrainingGroup;
use App\Domain\Training\Models\TrainingGroupPlayer;

class ListTrainingAttendanceAction
{
    /**
     * The attendance sheet of a group for one date: the current members sorted by name,
     * each with their record for that date or null. Players who left the group are not listed.
     *
     * @param  string  $date  `Y-m-d`
     * @return list<AttendanceRowData>
     */
    public function execute(TrainingGroup $group, string $date): array
    {
        $members = TrainingGroupPlayer::query()
            ->with('player')
            ->where('training_group_id', $group->id)
            ->get()
            ->sortBy(fn (TrainingGroupPlayer $member) => $member->player->name)
            ->values();

        $records = TrainingAttendance::query()
            ->where('training_group_id', $group->id)
            ->where('date', $date)
            ->get()
            ->keyBy('player_id');

        return $members
            ->map(fn (TrainingGroupPlayer $member) => new AttendanceRowData(
                $member->player,
                $records->get($member->player_id),
            ))
            ->all();
    }
}
