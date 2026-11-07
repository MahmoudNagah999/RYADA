<?php

namespace App\Domain\Training\DTOs;

final readonly class CreateTrainingGroupData
{
    /**
     * @param  list<ScheduleSlotData>  $schedules
     */
    public function __construct(
        public int $clubId,
        public int $sportId,
        public string $name,
        public ?int $coachId,
        public array $schedules,
        public bool $isActive = true,
    ) {}
}
