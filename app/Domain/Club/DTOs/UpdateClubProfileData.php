<?php

namespace App\Domain\Club\DTOs;

final readonly class UpdateClubProfileData
{
    public function __construct(
        public string $name,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public ?string $city,
        public ?string $address,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated request data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            contactEmail: $data['contact_email'] ?? null,
            contactPhone: $data['contact_phone'] ?? null,
            city: $data['city'] ?? null,
            address: $data['address'] ?? null,
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'contact_email' => $this->contactEmail,
            'contact_phone' => $this->contactPhone,
            'city' => $this->city,
            'address' => $this->address,
        ];
    }
}
