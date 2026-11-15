<?php

namespace App\Domain\Notification\Notifications;

use App\Domain\Notification\Enums\NotificationCode;
use App\Domain\Notification\Support\NotificationSchedule;

/**
 * The club's SaaS plan ends soon. The owner receives one per threshold and end date.
 *
 * The plan name is left out of the params: it is translatable, so it would need extra queries
 * at read time. The club name is left out too, for the same reason.
 */
class PlanExpiringNotification extends ClubDatabaseNotification
{
    /**
     * @param  int  $clubId  only feeds the dedup key, so it is not a param
     * @param  string  $endsOn  `Y-m-d` in the subscriptions timezone
     * @param  int  $daysLeft  whole calendar days between today and `$endsOn`
     * @param  int  $threshold  the configured reminder day this notification stands for
     */
    public function __construct(
        private readonly int $clubId,
        private readonly string $endsOn,
        private readonly int $daysLeft,
        private readonly int $threshold,
    ) {}

    public function code(): NotificationCode
    {
        return NotificationCode::ClubPlanExpiring;
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return [
            'ends_on' => $this->endsOn,
            'days_left' => $this->daysLeft,
            'threshold' => $this->threshold,
        ];
    }

    public function dedupKey(): string
    {
        return NotificationSchedule::planExpiringDedupKey($this->clubId, $this->endsOn, $this->threshold);
    }
}
