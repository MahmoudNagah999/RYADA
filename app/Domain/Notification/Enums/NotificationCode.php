<?php

namespace App\Domain\Notification\Enums;

/**
 * Stable machine-readable codes stored in `notifications.data.code`.
 * The API translates them at read time; clients branch on the code, never on the message.
 */
enum NotificationCode: string
{
    case ClubWelcome = 'club.welcome';
    case ClubPlanExpiring = 'club.plan_expiring';
    case SubscriptionExpiring = 'subscription.expiring';
    case SubscriptionUnpaid = 'subscription.unpaid';

    /**
     * Key in lang/en/notifications.php and lang/ar/notifications.php.
     *
     * A message that counts days down to the last one needs a singular sentence, and the bell
     * translates with `__()` (no `trans_choice`), so those codes have a separate `one` key.
     *
     * @param  array<string, mixed>  $params
     */
    public function translationKey(array $params = []): string
    {
        $key = 'notifications.'.$this->value;

        if ($this !== self::ClubPlanExpiring) {
            return $key;
        }

        return (int) ($params['days_left'] ?? 0) === 1 ? $key.'.one' : $key;
    }
}
