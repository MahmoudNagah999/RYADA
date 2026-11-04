<?php

namespace App\Domain\Player\DTOs;

use App\Domain\Player\Enums\Gender;
use Carbon\CarbonImmutable;

final readonly class UpdatePlayerData
{
    public function __construct(
        public string $name,
        public ?string $nationalId,
        public ?CarbonImmutable $birthDate,
        public ?Gender $gender,
        public ?string $phone,
    ) {}
}
