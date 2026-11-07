<?php

namespace App\Http\Requests\Training;

use App\Domain\Training\DTOs\AttendanceRecordData;
use App\Domain\Training\Enums\AttendanceStatus;
use App\Domain\Training\Models\TrainingGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RecordAttendanceRequest extends FormRequest
{
    /**
     * For an unknown (or other club's) group this returns true so the controller answers 404.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $group = TrainingGroup::query()->find((int) $this->route('group'));

        return $group === null || Gate::forUser($user)->allows('recordAttendance', $group);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'records' => ['required', 'array', 'min:1', 'max:200'],
            'records.*.player_id' => ['required', 'integer', 'distinct'],
            'records.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
        ];
    }

    /**
     * @return list<AttendanceRecordData>
     */
    public function attendanceRecords(): array
    {
        return collect($this->input('records', []))
            ->map(fn (array $record) => new AttendanceRecordData(
                (int) $record['player_id'],
                AttendanceStatus::from((string) $record['status']),
            ))
            ->values()
            ->all();
    }
}
