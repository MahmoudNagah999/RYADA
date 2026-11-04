<?php

namespace App\Http\Resources;

use App\Domain\Player\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Player
 */
class PlayerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'national_id' => $this->national_id,
            'birth_date' => $this->birth_date->toDateString(),
            'age' => $this->age(),
            'gender' => $this->gender->value,
            'phone' => $this->phone,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
