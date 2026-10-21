<?php

namespace App\Domain\Auth\DTOs;

use App\Models\User;

readonly class LoginResult
{
    /**
     * @param  array<int, string>  $abilities
     */
    public function __construct(
        public User $user,
        public string $token,
        public array $abilities,
    ) {}
}
