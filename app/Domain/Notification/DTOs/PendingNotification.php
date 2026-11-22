<?php

namespace App\Domain\Notification\DTOs;

use App\Domain\Notification\Notifications\ClubDatabaseNotification;

/**
 * A notification a notifier run is about to send, paired with the dedup key it carries.
 *
 * Built for a whole page of due subscriptions before any recipient is touched, so the keys
 * already stored for that page can be resolved in a single query.
 */
final readonly class PendingNotification
{
    public function __construct(
        public string $dedupKey,
        public ClubDatabaseNotification $notification,
    ) {}
}
