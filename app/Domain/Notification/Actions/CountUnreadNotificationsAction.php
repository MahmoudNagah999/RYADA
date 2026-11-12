<?php

namespace App\Domain\Notification\Actions;

use App\Models\User;

class CountUnreadNotificationsAction
{
    /**
     * Counts through the user's own relation only.
     */
    public function execute(User $user): int
    {
        return (int) $user->unreadNotifications()->count();
    }
}
