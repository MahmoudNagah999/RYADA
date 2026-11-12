<?php

namespace App\Domain\Notification\DTOs;

final readonly class ListUserNotificationsData
{
    public function __construct(
        public bool $unreadOnly,
        public int $perPage,
    ) {}
}
