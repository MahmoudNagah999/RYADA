<?php

namespace App\Domain\PlayerSubscription\DTOs;

use App\Domain\Membership\DTOs\MembershipDiscount;

final readonly class SubscribePlayerData
{
    /**
     * @param  string|null  $startsOn  `Y-m-d`, defaults to today in the subscription timezone.
     */
    public function __construct(
        public int $playerId,
        public int $membershipPlanId,
        public ?string $startsOn = null,
        public ?MembershipDiscount $discount = null,
    ) {}
}
