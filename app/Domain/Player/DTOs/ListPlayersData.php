<?php

namespace App\Domain\Player\DTOs;

use App\Domain\Player\Enums\Gender;

final readonly class ListPlayersData
{
    public function __construct(
        public ?string $search,
        public ?Gender $gender,
        public ?int $ageMin,
        public ?int $ageMax,
        public string $sortColumn,
        public string $sortDirection,
        public int $perPage,
    ) {}
}
