<?php

namespace App\Domain\Sport\DTOs;

final readonly class SportData
{
    /**
     * @param  array{en: string, ar: string}  $name
     */
    public function __construct(
        public string $slug,
        public array $name,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{en: string, ar: string} $name */
        $name = [
            'en' => (string) ($data['name']['en'] ?? ''),
            'ar' => (string) ($data['name']['ar'] ?? ''),
        ];

        return new self(
            slug: (string) $data['slug'],
            name: $name,
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }
}
