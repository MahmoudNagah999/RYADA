<?php

namespace App\Domain\Training\Actions;

use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Training\DTOs\ScheduleSlotData;

class ValidateScheduleSlotsAction
{
    /**
     * Each slot must end after it starts, and slots of the same day must not overlap.
     * Back-to-back slots (18:00 end, 18:00 start) are allowed. Times are zero-padded `H:i`.
     *
     * @param  list<ScheduleSlotData>  $slots
     */
    public function execute(array $slots): void
    {
        $byDay = [];

        foreach ($slots as $slot) {
            if ($slot->endTime <= $slot->startTime) {
                throw new BusinessRuleException('training_group.schedule_invalid', [], 422);
            }

            $byDay[$slot->dayOfWeek][] = $slot;
        }

        foreach ($byDay as $daySlots) {
            usort($daySlots, fn (ScheduleSlotData $a, ScheduleSlotData $b) => $a->startTime <=> $b->startTime);

            for ($i = 1; $i < count($daySlots); $i++) {
                if ($daySlots[$i]->startTime < $daySlots[$i - 1]->endTime) {
                    throw new BusinessRuleException('training_group.schedule_overlap', [], 422);
                }
            }
        }
    }
}
