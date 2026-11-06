<?php

namespace App\Domain\Membership\DTOs;

final readonly class CreateMembershipPlanData
{
    public function __construct(
        public int $sportId,
        public string $name,
        public int $priceCents,
        public int $durationMonths,
        public bool $isActive = true,
        public string $currency = 'EGP',
    ) {}
}
