<?php

namespace App\Domain\Staff\DTOs;

use App\Domain\Auth\Enums\RoleName;

final readonly class InviteStaffData
{
    public function __construct(
        public string $email,
        public RoleName $role,
        public string $locale,
    ) {}

    /**
     * @param  array{email: string, role: string, locale: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'],
            role: RoleName::from($data['role']),
            locale: $data['locale'],
        );
    }
}
