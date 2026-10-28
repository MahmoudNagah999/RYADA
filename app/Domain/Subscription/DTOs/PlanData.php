<?php

namespace App\Domain\Subscription\DTOs;

use App\Domain\Subscription\Models\Plan;

final readonly class PlanData
{
    /**
     * @param  array<string, string>  $name  Translations keyed by locale.
     */
    public function __construct(
        public string $slug,
        public array $name,
        public int $priceCents,
        public int $durationMonths,
        public ?int $maxPlayers,
        public ?int $maxCoaches,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated form data (snake_case keys).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            slug: (string) $data['slug'],
            name: (array) $data['name'],
            priceCents: (int) $data['price_cents'],
            durationMonths: (int) $data['duration_months'],
            maxPlayers: self::nullableInt($data['max_players'] ?? null),
            maxCoaches: self::nullableInt($data['max_coaches'] ?? null),
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    public static function fromPlan(Plan $plan): self
    {
        return new self(
            slug: $plan->slug,
            name: $plan->getTranslations('name'),
            priceCents: $plan->price_cents,
            durationMonths: $plan->duration_months,
            maxPlayers: $plan->max_players,
            maxCoaches: $plan->max_coaches,
            isActive: $plan->is_active,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'price_cents' => $this->priceCents,
            'duration_months' => $this->durationMonths,
            'max_players' => $this->maxPlayers,
            'max_coaches' => $this->maxCoaches,
            'is_active' => $this->isActive,
        ];
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
