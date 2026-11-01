<?php

namespace App\Domain\Staff\DTOs;

final readonly class AcceptStaffInvitationData
{
    public function __construct(
        public string $token,
        public string $name,
        public string $password,
        public string $locale,
    ) {}

    /**
     * @param  array{token: string, name: string, password: string, locale: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            token: $data['token'],
            name: $data['name'],
            password: $data['password'],
            locale: $data['locale'],
        );
    }
}
