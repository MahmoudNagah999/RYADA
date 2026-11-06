<?php

namespace App\Domain\Membership\DTOs;

final readonly class UpdateMembershipPlanData
{
    public function __construct(
        public string $name,
        public int $priceCents,
        public int $durationMonths,
        public bool $isActive,
        public string $currency = 'EGP',
    ) {}
}
