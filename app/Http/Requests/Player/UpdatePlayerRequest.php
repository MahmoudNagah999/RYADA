<?php

namespace App\Http\Requests\Player;

use App\Domain\Player\DTOs\UpdatePlayerData;
use App\Domain\Player\Models\Player;

class UpdatePlayerRequest extends PlayerFormRequest
{
    private ?Player $player = null;

    /**
     * Club scope applies: a player of another club is a 404 here, before any 403 or 422.
     */
    public function player(): Player
    {
        return $this->player ??= Player::query()->findOrFail($this->route('player'));
    }

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->player()) ?? false;
    }

    public function toData(): UpdatePlayerData
    {
        return new UpdatePlayerData(
            name: $this->playerName(),
            nationalId: $this->nationalId(),
            birthDate: $this->birthDate(),
            gender: $this->gender(),
            phone: $this->phone(),
        );
    }
}
