<?php

namespace App\Http\Resources;

use App\Domain\Sport\DTOs\SportSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SportSummary
 */
class SportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name(app()->getLocale(), (string) config('app.fallback_locale')),
        ];
    }
}
