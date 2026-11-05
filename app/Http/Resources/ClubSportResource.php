<?php

namespace App\Http\Resources;

use App\Domain\Sport\Models\ClubSport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClubSport
 */
class ClubSportResource extends JsonResource
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
            'is_active' => $this->sport->is_active,
            'added_at' => $this->created_at->toIso8601String(),
        ];
    }
}
