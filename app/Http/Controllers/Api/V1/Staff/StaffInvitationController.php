<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Domain\Club\Models\Club;
use App\Domain\Staff\Actions\InviteStaffAction;
use App\Domain\Staff\Actions\RevokeStaffInvitationAction;
use App\Domain\Staff\Models\StaffInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\InviteStaffRequest;
use App\Http\Resources\StaffInvitationResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class StaffInvitationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $actor */
        $actor = $request->user();

        Gate::authorize('viewAny', StaffInvitation::class);

        $invitations = StaffInvitation::query()
            ->where('club_id', $actor->club_id)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25);

        return StaffInvitationResource::collection($invitations);
    }

    public function store(InviteStaffRequest $request, InviteStaffAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        Gate::authorize('create', StaffInvitation::class);

        $club = Club::query()->findOrFail($actor->club_id);
        $invitation = $action->execute($club, $actor, $request->toDto());

        return (new StaffInvitationResource($invitation))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(
        Request $request,
        int $invitation,
        RevokeStaffInvitationAction $action,
    ): Response {
        /** @var User $actor */
        $actor = $request->user();

        $staffInvitation = StaffInvitation::query()
            ->where('club_id', $actor->club_id)
            ->findOrFail($invitation);

        Gate::authorize('manage', $staffInvitation);

        $action->execute($actor, $staffInvitation);

        return response()->noContent();
    }
}
