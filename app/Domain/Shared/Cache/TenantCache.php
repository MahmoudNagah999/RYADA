<?php

namespace App\Domain\Shared\Cache;

use App\Domain\Shared\Tenancy\CurrentClub;
use Closure;
use Illuminate\Contracts\Cache\Repository;
use LogicException;

/**
 * The only way to cache club-specific data.
 * Every key is built as "club:{id}:{name}", so one club can never read another club's entry.
 * Global reference data (sports, SaaS plans) does not use this class.
 */
final class TenantCache
{
    public function __construct(
        private readonly CurrentClub $currentClub,
        private readonly Repository $cache,
    ) {}

    public function key(string $name): string
    {
        return $this->keyFor($this->currentClubId(), $name);
    }

    public function keyFor(int $clubId, string $name): string
    {
        if ($name === '') {
            throw new LogicException('A tenant cache entry needs a name.');
        }

        return "club:{$clubId}:{$name}";
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function remember(string $name, int $ttlSeconds, Closure $callback): mixed
    {
        return $this->cache->remember($this->key($name), $ttlSeconds, $callback);
    }

    public function forget(string $name): bool
    {
        return $this->cache->forget($this->key($name));
    }

    public function forgetForClub(int $clubId, string $name): bool
    {
        return $this->cache->forget($this->keyFor($clubId, $name));
    }

    private function currentClubId(): int
    {
        $club = $this->currentClub->get();

        if ($club === null) {
            throw new LogicException('TenantCache was used without a current club.');
        }

        return $club->id;
    }
}
