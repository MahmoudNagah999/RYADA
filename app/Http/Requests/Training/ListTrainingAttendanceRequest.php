<?php

namespace App\Http\Requests\Training;

use App\Domain\Training\Models\TrainingGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ListTrainingAttendanceRequest extends FormRequest
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

        return $group === null || Gate::forUser($user)->allows('viewAttendance', $group);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
