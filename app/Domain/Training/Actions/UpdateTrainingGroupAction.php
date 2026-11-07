<?php

namespace App\Domain\Training\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Training\DTOs\ScheduleSlotData;
use App\Domain\Training\DTOs\UpdateTrainingGroupData;
use App\Domain\Training\Models\TrainingGroup;
use App\Domain\Training\Models\TrainingGroupSchedule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class UpdateTrainingGroupAction
{
    public function __construct(
        private readonly RecordActivityAction $recordActivity,
        private readonly ValidateScheduleSlotsAction $validateSlots,
        private readonly EnsureUserIsClubCoachAction $ensureCoach,
    ) {}

    /**
     * Replaces name, coach, active flag and the weekly schedule. The sport never changes.
     * The coach role is checked only when the coach actually changes (a coach who was later
     * moved to another role does not block edits to the group). No changes means no audit row.
     */
    public function execute(TrainingGroup $group, UpdateTrainingGroupData $data): TrainingGroup
    {
        $this->validateSlots->execute($data->schedules);

        return DB::transaction(function () use ($group, $data): TrainingGroup {
            $after = [
                'name' => $data->name,
                'coach_id' => $data->coachId,
                'is_active' => $data->isActive,
            ];

            $changes = [];
            foreach ($after as $field => $to) {
                $from = $group->getAttribute($field);

                if ($from !== $to) {
                    $changes[$field] = ['from' => $from, 'to' => $to];
                }
            }

            $currentSchedule = $group->schedules
                ->map(fn (TrainingGroupSchedule $s) => $s->day_of_week->value.' '.substr($s->start_time, 0, 5).'-'.substr($s->end_time, 0, 5))
                ->sort()
                ->values()
                ->all();
            $newSchedule = collect($data->schedules)
                ->map(fn (ScheduleSlotData $s) => $s->label())
                ->sort()
                ->values()
                ->all();

            if ($currentSchedule !== $newSchedule) {
                $changes['schedules'] = ['from' => $currentSchedule, 'to' => $newSchedule];
            }

            if ($changes === []) {
                return $group;
            }

            if (isset($changes['coach_id']) && $data->coachId !== null) {
                $this->ensureCoach->execute($data->coachId, $group->club_id);
            }

            if (isset($changes['name']) && TrainingGroup::query()
                ->where('sport_id', $group->sport_id)
                ->where('name', $data->name)
                ->whereKeyNot($group->id)
                ->exists()) {
                throw new BusinessRuleException('training_group.name_taken', [], 422);
            }

            try {
                $group->fill($after)->save();
            } catch (UniqueConstraintViolationException) {
                throw new BusinessRuleException('training_group.name_taken', [], 422);
            }

            if (isset($changes['schedules'])) {
                TrainingGroupSchedule::query()->where('training_group_id', $group->id)->delete();

                foreach ($data->schedules as $slot) {
                    $group->schedules()->create([
                        'day_of_week' => $slot->dayOfWeek,
                        'start_time' => $slot->startTime.':00',
                        'end_time' => $slot->endTime.':00',
                    ]);
                }

                $group->unsetRelation('schedules');
            }

            $this->recordActivity->execute(
                ActivityEvent::TrainingGroupUpdated,
                $group,
                ['group_id' => $group->id, 'changes' => $changes],
                null,
                null,
            );

            return $group;
        });
    }
}
