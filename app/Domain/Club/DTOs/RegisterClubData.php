<?php

namespace App\Domain\Club\DTOs;

readonly class RegisterClubData
{
    public function __construct(
        public string $clubName,
        public string $ownerName,
        public string $email,
        public string $password,
        public string $locale,
    ) {}
}
