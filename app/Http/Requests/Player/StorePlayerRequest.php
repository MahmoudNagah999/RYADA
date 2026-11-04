<?php

namespace App\Http\Requests\Player;

use App\Domain\Player\DTOs\CreatePlayerData;
use App\Domain\Player\Models\Player;

class StorePlayerRequest extends PlayerFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Player::class) ?? false;
    }

    public function toData(): CreatePlayerData
    {
        return new CreatePlayerData(
            name: $this->playerName(),
            nationalId: $this->nationalId(),
            birthDate: $this->birthDate(),
            gender: $this->gender(),
            phone: $this->phone(),
        );
    }
}
