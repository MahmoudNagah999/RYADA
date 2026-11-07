<?php

namespace App\Domain\Training\DTOs;

use App\Domain\Player\Models\Player;
use App\Domain\Training\Models\TrainingAttendance;

/**
 * One line of a group's attendance sheet: a member and their record for the date (null = not recorded).
 */
final readonly class AttendanceRowData
{
    public function __construct(
        public Player $player,
        public ?TrainingAttendance $attendance,
    ) {}
}
