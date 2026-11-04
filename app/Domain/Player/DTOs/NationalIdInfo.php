<?php

namespace App\Domain\Player\DTOs;

use App\Domain\Player\Enums\Gender;
use Carbon\CarbonImmutable;

final readonly class NationalIdInfo
{
    public function __construct(
        public CarbonImmutable $birthDate,
        public Gender $gender,
    ) {}
}
