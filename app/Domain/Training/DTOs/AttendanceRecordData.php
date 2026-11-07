<?php

namespace App\Domain\Training\DTOs;

use App\Domain\Training\Enums\AttendanceStatus;

final readonly class AttendanceRecordData
{
    public function __construct(
        public int $playerId,
        public AttendanceStatus $status,
    ) {}
}
