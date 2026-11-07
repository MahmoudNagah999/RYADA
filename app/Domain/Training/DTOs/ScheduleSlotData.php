<?php

namespace App\Domain\Training\DTOs;

final readonly class ScheduleSlotData
{
    /**
     * @param  string  $startTime  `H:i`
     * @param  string  $endTime  `H:i`
     */
    public function __construct(
        public int $dayOfWeek,
        public string $startTime,
        public string $endTime,
    ) {}

    public function label(): string
    {
        return "{$this->dayOfWeek} {$this->startTime}-{$this->endTime}";
    }
}
