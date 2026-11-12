<?php

namespace App\Domain\Sport\DTOs;

use App\Domain\Sport\Models\ClubSport;

final readonly class ClubSportView
{
    public function __construct(
        public SportSummary $sport,
        public string $addedAt,
    ) {}

    public static function fromModel(ClubSport $clubSport): self
    {
        return new self(
            SportSummary::fromModel($clubSport->sport),
            $clubSport->created_at->toIso8601String(),
        );
    }
}
