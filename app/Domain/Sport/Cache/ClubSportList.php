<?php

namespace App\Domain\Sport\Cache;

use App\Domain\Shared\Cache\TenantCache;
use App\Domain\Sport\DTOs\ClubSportView;
use App\Domain\Sport\Models\ClubSport;
use Illuminate\Support\Collection;

/**
 * The sports of the current club. The tenant entry holds only sport ids and dates;
 * names and flags come from the shared catalog, so renaming a sport never leaves a stale club entry.
 * The entry is cleared by ClubSportObserver (after commit).
 */
final class ClubSportList
{
    public const KEY = 'sports';

    public const TTL_SECONDS = 3600;

    public function __construct(
        private readonly TenantCache $cache,
        private readonly SportCatalog $catalog,
    ) {}

    /**
     * @return Collection<int, ClubSportView>
     */
    public function forCurrentClub(): Collection
    {
        /** @var list<array{sport_id: int, added_at: string}> $rows */
        $rows = $this->cache->remember(self::KEY, self::TTL_SECONDS, function (): array {
            return ClubSport::query()
                ->get(['sport_id', 'created_at'])
                ->map(fn (ClubSport $clubSport): array => [
                    'sport_id' => $clubSport->sport_id,
                    'added_at' => $clubSport->created_at->toIso8601String(),
                ])
                ->all();
        });

        $catalog = $this->catalog->all()->keyBy('id');

        return collect($rows)
            ->map(function (array $row) use ($catalog): ?ClubSportView {
                $sport = $catalog->get($row['sport_id']);

                return $sport === null ? null : new ClubSportView($sport, $row['added_at']);
            })
            ->filter()
            ->sortBy(fn (ClubSportView $view): string => $view->sport->slug)
            ->values();
    }
}
