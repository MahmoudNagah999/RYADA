<?php

namespace App\Domain\PlayerSubscription\DTOs;

use App\Domain\PlayerSubscription\Enums\SubscriptionStatus;

final readonly class ListPlayerSubscriptionsData
{
    public function __construct(
        public ?int $playerId,
        public ?int $membershipPlanId,
        public ?SubscriptionStatus $status,
        public string $sortColumn,
        public string $sortDirection,
        public int $perPage,
    ) {}
}
