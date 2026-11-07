<?php

namespace App\Domain\Training\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Excused = 'excused';
}
