<?php

namespace App\Domain\Training\DTOs;

final readonly class RecordAttendanceData
{
    /**
     * @param  string  $date  `Y-m-d`
     * @param  list<AttendanceRecordData>  $records
     */
    public function __construct(
        public int $recordedBy,
        public string $date,
        public array $records,
    ) {}
}
