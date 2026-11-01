<?php

namespace App\Http\Resources;

use App\Domain\Club\Models\Club;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Club
 */
class ClubResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_url' => $this->logo_path
                ? Storage::disk(config('filesystems.club_logo_disk'))->url($this->logo_path)
                : null,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'city' => $this->city,
            'address' => $this->address,
        ];
    }
}
