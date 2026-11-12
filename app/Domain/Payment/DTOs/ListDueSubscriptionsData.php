<?php

namespace App\Domain\Payment\DTOs;

final readonly class ListDueSubscriptionsData
{
    /**
     * @param  string  $type  `expiring`, `unpaid` or `all`.
     * @param  int  $expiringWithinDays  0 to 60 days ahead.
     * @param  int  $page  1-based page number.
     */
    public function __construct(
        public string $type,
        public int $expiringWithinDays,
        public int $perPage,
        public int $page = 1,
    ) {}
}
