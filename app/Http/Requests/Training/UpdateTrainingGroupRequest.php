<?php

namespace App\Http\Requests\Training;

use App\Domain\Training\Models\TrainingGroup;
use Illuminate\Support\Facades\Gate;

class UpdateTrainingGroupRequest extends TrainingGroupFormRequest
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

        return $group === null || Gate::forUser($user)->allows('update', $group);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->sharedRules(), [
            'sport_id' => ['prohibited'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
