<?php

namespace App\Domain\Notification\Notifications;

use App\Domain\Notification\Enums\NotificationCode;
use Illuminate\Notifications\Notification;

/**
 * Base for every club notification. The bell (database channel) is the only channel and
 * sending is one insert, written synchronously by the caller, so this class is never queued.
 *
 * The row keeps the stable code and its params; the message is translated when it is read
 * (ADR-003), so a language switch also re-translates existing notifications.
 */
abstract class ClubDatabaseNotification extends Notification
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    abstract public function code(): NotificationCode;

    /**
     * @return array<string, mixed>
     */
    abstract public function params(): array;

    /**
     * A null key means "always send"; otherwise a recipient never gets the same key twice.
     */
    public function dedupKey(): ?string
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'code' => $this->code()->value,
            'params' => $this->params(),
            'dedup_key' => $this->dedupKey(),
        ];
    }
}
