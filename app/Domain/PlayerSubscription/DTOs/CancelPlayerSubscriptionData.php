<?php

namespace App\Domain\PlayerSubscription\DTOs;

final readonly class CancelPlayerSubscriptionData
{
    public function __construct(
        public ?int $cancelledBy = null,
    ) {}
}
