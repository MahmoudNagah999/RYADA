<?php

namespace App\Domain\Shared\Tenancy;

use App\Domain\Club\Models\Club;
use Closure;

class CurrentClub
{
    private ?Club $club = null;

    public function set(?Club $club): void
    {
        $this->club = $club;
    }

    public function get(): ?Club
    {
        return $this->club;
    }

    public function id(): ?int
    {
        return $this->club?->getKey();
    }

    public function has(): bool
    {
        return $this->club !== null;
    }

    /**
     * Run a callback inside a club context, then restore the previous one.
     * Use it in jobs, seeders, commands and tests.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(Club $club, Closure $callback): mixed
    {
        $previous = $this->club;
        $this->club = $club;

        try {
            return $callback();
        } finally {
            $this->club = $previous;
        }
    }
}
