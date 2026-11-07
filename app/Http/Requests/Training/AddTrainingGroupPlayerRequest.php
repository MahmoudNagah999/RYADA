<?php

namespace App\Http\Requests\Training;

use App\Domain\Training\Models\TrainingGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AddTrainingGroupPlayerRequest extends FormRequest
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
     * `exists` is not tenant-scoped, so it is limited to the caller's club: a player of another
     * club fails exactly like a player that does not exist.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'player_id' => [
                'required',
                'integer',
                Rule::exists('players', 'id')->where('club_id', $this->user()?->club_id),
            ],
        ];
    }
}
