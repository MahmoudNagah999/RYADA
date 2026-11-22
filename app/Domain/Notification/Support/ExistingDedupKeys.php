<?php

namespace App\Domain\Notification\Support;

/**
 * In-memory set of "{recipient id}|{dedup key}" fingerprints.
 *
 * A notifier loads the fingerprints already stored in `notifications` once per page, then adds
 * a fingerprint after every insert. That keeps the old guarantees - a second run creates
 * nothing, and one run never sends the same key twice to the same recipient - without one
 * `exists()` query per subscription and recipient.
 */
final class ExistingDedupKeys
{
    /** @var array<string, true> */
    private array $fingerprints = [];

    /**
     * @param  iterable<string>  $fingerprints
     */
    public function merge(iterable $fingerprints): void
    {
        foreach ($fingerprints as $fingerprint) {
            $this->fingerprints[$fingerprint] = true;
        }
    }

    public function add(int $recipientId, string $dedupKey): void
    {
        $this->fingerprints[self::fingerprint($recipientId, $dedupKey)] = true;
    }

    public function contains(int $recipientId, string $dedupKey): bool
    {
        return isset($this->fingerprints[self::fingerprint($recipientId, $dedupKey)]);
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return array_keys($this->fingerprints);
    }

    public static function fingerprint(int $recipientId, string $dedupKey): string
    {
        return $recipientId.'|'.$dedupKey;
    }
}
