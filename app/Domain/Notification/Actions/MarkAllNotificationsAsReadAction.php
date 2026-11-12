<?php

namespace App\Domain\Notification\Actions;

use App\Models\User;

class MarkAllNotificationsAsReadAction
{
    /**
     * A single bulk update on the user's own unread rows. Calling it again marks nothing.
     *
     * @return int number of notifications marked as read
     */
    public function execute(User $user): int
    {
        return (int) $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
