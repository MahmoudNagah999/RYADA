<?php

namespace App\Domain\Notification\Notifications;

use App\Domain\Notification\Enums\NotificationCode;
use App\Domain\Notification\Support\NotificationSchedule;

/**
 * A subscription ends within the configured window.
 */
class SubscriptionExpiringNotification extends ClubDatabaseNotification
{
    public function __construct(
        private readonly int $subscriptionId,
        private readonly int $playerId,
        private readonly string $playerName,
        private readonly string $planName,
        private readonly string $endsOn,
        private readonly int $daysLeft,
    ) {}

    public function code(): NotificationCode
    {
        return NotificationCode::SubscriptionExpiring;
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
            'ends_on' => $this->endsOn,
            'days_left' => $this->daysLeft,
        ];
    }

    public function dedupKey(): string
    {
        return NotificationSchedule::expiringDedupKey($this->subscriptionId, $this->endsOn);
    }
}
