<?php

namespace App\Domain\Sport\DTOs;

use App\Domain\Sport\Models\Sport;

final readonly class SportSummary
{
    /**
     * @param  array<string, string>  $names  every translation, keyed by locale
     */
    public function __construct(
        public int $id,
        public string $slug,
        public bool $isActive,
        public array $names,
    ) {}

    public static function fromModel(Sport $sport): self
    {
        /** @var array<string, string> $names */
        $names = $sport->getTranslations('name');

        return new self($sport->id, $sport->slug, (bool) $sport->is_active, $names);
    }

    /**
     * @param  array{id: int, slug: string, is_active: bool, names: array<string, string>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['id'], $data['slug'], $data['is_active'], $data['names']);
    }

    /**
     * @return array{id: int, slug: string, is_active: bool, names: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'is_active' => $this->isActive,
            'names' => $this->names,
        ];
    }

    /**
     * The name in the requested locale, then in the fallback locale, otherwise an empty string.
     */
    public function name(string $locale, string $fallbackLocale): string
    {
        foreach ([$locale, $fallbackLocale] as $candidate) {
            $name = $this->names[$candidate] ?? '';

            if ($name !== '') {
                return $name;
            }
        }

        return '';
    }
}
