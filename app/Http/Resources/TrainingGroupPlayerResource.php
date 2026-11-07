<?php

namespace App\Http\Resources;

use App\Domain\Training\Models\TrainingGroupPlayer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrainingGroupPlayer
 */
class TrainingGroupPlayerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'player_id' => $this->player_id,
            'name' => $this->player->name,
            'added_at' => $this->created_at->toIso8601String(),
        ];
    }
}
