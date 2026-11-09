<?php

namespace App\Domain\Sport\Cache;

use App\Domain\Sport\DTOs\SportSummary;
use App\Domain\Sport\Models\Sport;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;

/**
 * Global reference data: the same cache entry serves every club and every locale.
 * It holds all translations; the name is chosen when the response is built.
 * The entry is cleared by SportCatalogObserver (after commit).
 */
final class SportCatalog
{
    public const KEY = 'sports:catalog';

    public const TTL_SECONDS = 3600;

    public function __construct(private readonly Repository $cache) {}

    /**
     * All sports (active and inactive), sorted by slug.
     *
     * @return Collection<int, SportSummary>
     */
    public function all(): Collection
    {
        /** @var list<array{id: int, slug: string, is_active: bool, names: array<string, string>}> $rows */
        $rows = $this->cache->remember(self::KEY, self::TTL_SECONDS, function (): array {
            return Sport::query()
                ->orderBy('slug')
                ->get()
                ->map(fn (Sport $sport): array => SportSummary::fromModel($sport)->toArray())
                ->all();
        });

        return collect($rows)->map(fn (array $row): SportSummary => SportSummary::fromArray($row));
    }

    /**
     * @return Collection<int, SportSummary>
     */
    public function active(): Collection
    {
        return $this->all()->filter(fn (SportSummary $sport): bool => $sport->isActive)->values();
    }

    public function forget(): void
    {
        $this->cache->forget(self::KEY);
    }
}
