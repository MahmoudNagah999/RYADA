<?php

namespace App\Domain\Notification\Actions;

use App\Domain\Notification\DTOs\ListUserNotificationsData;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

class ListUserNotificationsAction
{
    /**
     * Everything goes through the user's own relation, so a user can only ever read their rows.
     * The relation already sorts newest first (`latest('created_at')`).
     *
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    public function execute(User $user, ListUserNotificationsData $data): LengthAwarePaginator
    {
        $query = $user->notifications();

        if ($data->unreadOnly) {
            $query->whereNull('read_at');
        }

        /** @var LengthAwarePaginator<int, DatabaseNotification> $paginator */
        $paginator = $query->paginate($data->perPage);

        return $paginator;
    }
}
