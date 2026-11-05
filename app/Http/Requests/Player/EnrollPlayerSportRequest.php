<?php

namespace App\Http\Requests\Player;

use App\Domain\Player\Models\Player;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class EnrollPlayerSportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $player = Player::query()->find((int) $this->route('player'));

        // Unknown (or other-club) player: let the controller answer 404.
        return $user !== null && ($player === null || Gate::forUser($user)->allows('update', $player));
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
