<?php

namespace App\Http\Requests\Training;

use App\Domain\Training\Models\TrainingGroup;
use Illuminate\Support\Facades\Gate;

class StoreTrainingGroupRequest extends TrainingGroupFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && Gate::forUser($user)->allows('create', TrainingGroup::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->sharedRules(), [
            'sport_id' => ['required', 'integer', 'exists:sports,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
