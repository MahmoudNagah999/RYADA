<?php

namespace App\Domain\Notification\Enums;

/**
 * Stable machine-readable codes stored in `notifications.data.code`.
 * The API translates them at read time; clients branch on the code, never on the message.
 */
enum NotificationCode: string
{
    case ClubWelcome = 'club.welcome';
    case SubscriptionExpiring = 'subscription.expiring';
    case SubscriptionUnpaid = 'subscription.unpaid';

    /**
     * Key in lang/en/notifications.php and lang/ar/notifications.php.
     */
    public function translationKey(): string
    {
        return 'notifications.'.$this->value;
    }
}
