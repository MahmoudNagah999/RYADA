<?php

namespace App\Domain\Training\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Training\DTOs\AttendanceRecordData;
use App\Domain\Training\DTOs\RecordAttendanceData;
use App\Domain\Training\Models\TrainingAttendance;
use App\Domain\Training\Models\TrainingGroup;
use App\Domain\Training\Models\TrainingGroupPlayer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecordTrainingAttendanceAction
{
    public function __construct(private readonly RecordActivityAction $recordActivity) {}

    /**
     * Runs in the current club context. All or nothing: every player must be a member of the group,
     * the group must be active and the date must not be in the future (club timezone, see
     * config/attendance.php). Recording a date again updates the existing rows (upsert); rows
     * that already hold the same status are untouched, and a call that changes nothing is not logged.
     */
    public function execute(TrainingGroup $group, RecordAttendanceData $data): void
    {
        if ($data->date > Carbon::now(config('attendance.timezone'))->toDateString()) {
            throw new BusinessRuleException('attendance.date_in_future', [], 422);
        }

        DB::transaction(function () use ($group, $data): void {
            // Serializes concurrent bulk calls for the same group, so the unique index is never hit.
            $locked = TrainingGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();

            if (! $locked->is_active) {
                throw new BusinessRuleException('training_group.inactive', [], 422);
            }

            // Last entry wins if a player is listed twice (the HTTP layer rejects duplicates anyway).
            $records = collect($data->records)->keyBy(fn (AttendanceRecordData $record) => $record->playerId);
            $playerIds = $records->keys()->all();

            $members = TrainingGroupPlayer::query()
                ->where('training_group_id', $locked->id)
                ->whereIn('player_id', $playerIds)
                ->count();

            if ($members !== count($playerIds)) {
                throw new BusinessRuleException('attendance.player_not_in_group', [], 422);
            }

            $existing = TrainingAttendance::query()
                ->where('training_group_id', $locked->id)
                ->where('date', $data->date)
                ->whereIn('player_id', $playerIds)
                ->get()
                ->keyBy('player_id');

            $created = 0;
            $updated = 0;
            $unchanged = 0;

            foreach ($records as $playerId => $record) {
                $current = $existing->get($playerId);

                if ($current === null) {
                    TrainingAttendance::query()->create([
                        'training_group_id' => $locked->id,
                        'player_id' => $playerId,
                        'date' => $data->date,
                        'status' => $record->status,
                        'recorded_by' => $data->recordedBy,
                    ]);
                    $created++;
                } elseif ($current->status !== $record->status) {
                    $current->update([
                        'status' => $record->status,
                        'recorded_by' => $data->recordedBy,
                    ]);
                    $updated++;
                } else {
                    $unchanged++;
                }
            }

            if ($created + $updated === 0) {
                return;
            }

            $this->recordActivity->execute(
                ActivityEvent::TrainingGroupAttendanceRecorded,
                $locked,
                [
                    'group_id' => $locked->id,
                    'date' => $data->date,
                    'created' => $created,
                    'updated' => $updated,
                    'unchanged' => $unchanged,
                ],
                null,
                null,
            );
        });
    }
}
