<?php

namespace App\Domain\Training\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Sport\Models\ClubSport;
use App\Domain\Sport\Models\Sport;
use App\Domain\Training\DTOs\CreateTrainingGroupData;
use App\Domain\Training\DTOs\ScheduleSlotData;
use App\Domain\Training\Models\TrainingGroup;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CreateTrainingGroupAction
{
    public function __construct(
        private readonly RecordActivityAction $recordActivity,
        private readonly ValidateScheduleSlotsAction $validateSlots,
        private readonly EnsureUserIsClubCoachAction $ensureCoach,
    ) {}

    /**
     * Runs in the current club context. The sport must be offered by the club and active;
     * the coach (optional) must have the Coach role in the same club at this moment.
     */
    public function execute(CreateTrainingGroupData $data): TrainingGroup
    {
        $this->validateSlots->execute($data->schedules);

        return DB::transaction(function () use ($data): TrainingGroup {
            $sport = Sport::query()->findOrFail($data->sportId);

            if (! ClubSport::query()->where('sport_id', $sport->id)->exists()) {
                throw new BusinessRuleException('training_group.sport_not_offered', [], 422);
            }

            if (! $sport->is_active) {
                throw new BusinessRuleException('sport.inactive', [], 422);
            }

            if ($data->coachId !== null) {
                $this->ensureCoach->execute($data->coachId, $data->clubId);
            }

            if (TrainingGroup::query()
                ->where('sport_id', $sport->id)
                ->where('name', $data->name)
                ->exists()) {
                throw new BusinessRuleException('training_group.name_taken', [], 422);
            }

            try {
                $group = TrainingGroup::query()->create([
                    'sport_id' => $sport->id,
                    'coach_id' => $data->coachId,
                    'name' => $data->name,
                    'is_active' => $data->isActive,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new BusinessRuleException('training_group.name_taken', [], 422);
            }

            foreach ($data->schedules as $slot) {
                $group->schedules()->create([
                    'day_of_week' => $slot->dayOfWeek,
                    'start_time' => $slot->startTime.':00',
                    'end_time' => $slot->endTime.':00',
                ]);
            }

            $this->recordActivity->execute(
                ActivityEvent::TrainingGroupCreated,
                $group,
                [
                    'group_id' => $group->id,
                    'sport' => $sport->slug,
                    'name' => $group->name,
                    'coach_id' => $group->coach_id,
                    'schedules' => array_map(fn (ScheduleSlotData $s) => $s->label(), $data->schedules),
                ],
                null,
                null,
            );

            return $group;
        });
    }
}
