<?php

namespace App\Domain\Payment\DTOs;

use App\Domain\PlayerSubscription\Models\PlayerSubscription;

/**
 * One row of the due-subscriptions list: the subscription plus its computed balance.
 */
final readonly class DueSubscriptionData
{
    public function __construct(
        public PlayerSubscription $subscription,
        public SubscriptionBalance $balance,
    ) {}
}
