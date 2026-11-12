<?php

namespace App\Domain\Notification\Notifications;

use App\Domain\Notification\Enums\NotificationCode;
use App\Domain\Notification\Support\NotificationSchedule;
use Illuminate\Support\Carbon;

/**
 * A subscription still has an outstanding balance. Deduplicated per ISO week, so the
 * same unpaid subscription reminds at most once a week.
 */
class SubscriptionUnpaidNotification extends ClubDatabaseNotification
{
    /**
     * @param  string  $today  `Y-m-d` in the subscriptions timezone; it only drives the dedup key.
     */
    public function __construct(
        private readonly int $subscriptionId,
        private readonly int $playerId,
        private readonly string $playerName,
        private readonly string $planName,
        private readonly int $outstandingCents,
        private readonly string $currency,
        private readonly string $today,
    ) {}

    public function code(): NotificationCode
    {
        return NotificationCode::SubscriptionUnpaid;
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return [
            'subscription_id' => $this->subscriptionId,
            'player_id' => $this->playerId,
            'player_name' => $this->playerName,
            'plan_name' => $this->planName,
            'outstanding_cents' => $this->outstandingCents,
            'currency' => $this->currency,
        ];
    }

    public function dedupKey(): string
    {
        return NotificationSchedule::unpaidDedupKey($this->subscriptionId, Carbon::parse($this->today));
    }
}
