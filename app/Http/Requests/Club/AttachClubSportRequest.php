<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AttachClubSportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->club !== null && Gate::forUser($user)->allows('update', $user->club);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'sport_id' => ['required', 'integer', 'exists:sports,id'],
        ];
    }
}
