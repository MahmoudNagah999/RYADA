<?php

namespace App\Domain\Notification\Notifications;

use App\Domain\Notification\Enums\NotificationCode;

/**
 * Sent to the Owner right after a club is registered. Always sent, never deduplicated.
 */
class WelcomeClubNotification extends ClubDatabaseNotification
{
    public function __construct(private readonly string $clubName) {}

    public function code(): NotificationCode
    {
        return NotificationCode::ClubWelcome;
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return ['club_name' => $this->clubName];
    }
}
