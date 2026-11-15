<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class StaffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            // The caller loads `roles` (the list already does); the resource never
            // starts a query of its own, so a missing eager load is a bug, not a query.
            'role' => $this->whenLoaded('roles', fn () => $this->getRoleNames()->first()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
