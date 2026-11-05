<?php

namespace App\Http\Resources;

use App\Domain\Sport\Models\PlayerSport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlayerSport
 */
class PlayerSportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'sport_id' => $this->sport_id,
            'slug' => $this->sport->slug,
            'name' => $this->sport->name,
            'enrolled_at' => $this->created_at->toIso8601String(),
        ];
    }
}
