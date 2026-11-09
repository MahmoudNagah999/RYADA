<?php

namespace App\Http\Resources;

use App\Domain\Sport\DTOs\ClubSportView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClubSportView
 */
class ClubSportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'sport_id' => $this->sport->id,
            'slug' => $this->sport->slug,
            'name' => $this->sport->name(app()->getLocale(), (string) config('app.fallback_locale')),
            'is_active' => $this->sport->isActive,
            'added_at' => $this->addedAt,
        ];
    }
}
