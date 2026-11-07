<?php

namespace App\Http\Resources;

use App\Domain\Training\DTOs\AttendanceRowData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainingAttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AttendanceRowData $row */
        $row = $this->resource;

        return [
            'player_id' => $row->player->id,
            'name' => $row->player->name,
            'status' => $row->attendance?->status->value,
            'recorded_at' => $row->attendance?->updated_at->toIso8601String(),
        ];
    }
}
