<?php

namespace App\Domain\Notification\Actions;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

class MarkNotificationAsReadAction
{
    /**
     * Only the user's own rows are reachable, so an unknown id is a 404. Idempotent:
     * an already read row keeps its original `read_at`.
     */
    public function execute(User $user, string $notificationId): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();

        $notification->markAsRead();

        return $notification;
    }
}
