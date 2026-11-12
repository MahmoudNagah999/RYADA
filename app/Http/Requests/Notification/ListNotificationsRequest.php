<?php

namespace App\Http\Requests\Notification;

use App\Domain\Notification\DTOs\ListUserNotificationsData;
use Illuminate\Foundation\Http\FormRequest;

class ListNotificationsRequest extends FormRequest
{
    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 100;

    /**
     * The relation always scopes every query to the authenticated user, so anyone
     * with a valid token may list their own bell.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'unread' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function toData(): ListUserNotificationsData
    {
        return new ListUserNotificationsData(
            unreadOnly: $this->boolean('unread'),
            perPage: min($this->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE),
        );
    }
}
