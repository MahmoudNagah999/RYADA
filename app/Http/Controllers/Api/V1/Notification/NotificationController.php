<?php

namespace App\Http\Controllers\Api\V1\Notification;

use App\Domain\Notification\Actions\CountUnreadNotificationsAction;
use App\Domain\Notification\Actions\ListUserNotificationsAction;
use App\Domain\Notification\Actions\MarkAllNotificationsAsReadAction;
use App\Domain\Notification\Actions\MarkNotificationAsReadAction;
use App\Http\Requests\Notification\ListNotificationsRequest;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController
{
    public function index(
        ListNotificationsRequest $request,
        ListUserNotificationsAction $action,
    ): AnonymousResourceCollection {
        return NotificationResource::collection($action->execute($this->user($request), $request->toData()));
    }

    public function unreadCount(
        Request $request,
        CountUnreadNotificationsAction $action,
    ): JsonResponse {
        return new JsonResponse(['data' => ['unread' => $action->execute($this->user($request))]]);
    }

    public function read(
        Request $request,
        string $notification,
        MarkNotificationAsReadAction $action,
    ): NotificationResource {
        return new NotificationResource($action->execute($this->user($request), $notification));
    }

    public function readAll(
        Request $request,
        MarkAllNotificationsAsReadAction $action,
    ): JsonResponse {
        return new JsonResponse(['data' => ['marked' => $action->execute($this->user($request))]]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_if(! $user instanceof User, 401);

        return $user;
    }
}
