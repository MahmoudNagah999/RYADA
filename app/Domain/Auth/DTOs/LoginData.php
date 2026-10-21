<?php

namespace App\Domain\Auth\DTOs;

readonly class LoginData
{
    /**
     * @param  array<int, string>  $abilities
     */
    public function __construct(
        public string $email,
        public string $password,
        public string $deviceName = 'api',
        public array $abilities = ['*'],
    ) {}
}
