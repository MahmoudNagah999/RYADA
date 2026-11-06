<?php

namespace App\Domain\Membership\DTOs;

final readonly class AmountBreakdown
{
    public function __construct(
        public int $priceCents,
        public int $discountCents,
        public int $totalCents,
    ) {}
}
