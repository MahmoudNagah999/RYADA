<?php

namespace App\Domain\Training\DTOs;

final readonly class UpdateTrainingGroupData
{
    /**
     * @param  list<ScheduleSlotData>  $schedules
     */
    public function __construct(
        public string $name,
        public ?int $coachId,
        public bool $isActive,
        public array $schedules,
    ) {}
}
