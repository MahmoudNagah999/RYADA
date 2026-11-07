<?php

use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Training\Actions\ValidateScheduleSlotsAction;
use App\Domain\Training\DTOs\ScheduleSlotData;

function validateSlots(array $slots): void
{
    (new ValidateScheduleSlotsAction)->execute($slots);
}

it('accepts back-to-back slots and the same time on different days', function () {
    validateSlots([
        new ScheduleSlotData(1, '17:00', '18:00'),
        new ScheduleSlotData(1, '18:00', '19:00'),
        new ScheduleSlotData(3, '17:00', '18:00'),
    ]);

    expect(true)->toBeTrue();
});

it('rejects overlapping slots on the same day', function () {
    validateSlots([
        new ScheduleSlotData(1, '17:00', '18:30'),
        new ScheduleSlotData(1, '18:00', '19:00'),
    ]);
})->throws(BusinessRuleException::class);

it('rejects a slot that ends before or when it starts', function (string $end) {
    validateSlots([new ScheduleSlotData(1, '17:00', $end)]);
})->with(['16:00', '17:00'])->throws(BusinessRuleException::class);
