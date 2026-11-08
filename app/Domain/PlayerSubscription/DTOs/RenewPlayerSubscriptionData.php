<?php

namespace App\Domain\PlayerSubscription\DTOs;

use App\Domain\Membership\DTOs\MembershipDiscount;

final readonly class RenewPlayerSubscriptionData
{
    public function __construct(
        public ?MembershipDiscount $discount = null,
    ) {}
}
