<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Domain\Staff\Actions\ChangeStaffRoleAction;
use App\Domain\Staff\Actions\ListStaffAction;
use App\Domain\Staff\Actions\RemoveStaffAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ChangeStaffRoleRequest;
use App\Http\Requests\Staff\ListStaffRequest;
use App\Http\Resources\StaffResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class StaffController extends Controller
{
    public function index(ListStaffRequest $request, ListStaffAction $action): AnonymousResourceCollection
    {
        /** @var User $actor */
        $actor = $request->user();

        Gate::authorize('viewAny', User::class);

        return StaffResource::collection($action->execute($actor, $request->toDto()));
    }

    public function updateRole(
        ChangeStaffRoleRequest $request,
        int $user,
        ChangeStaffRoleAction $action,
    ): StaffResource {
        /** @var User $actor */
        $actor = $request->user();

        $staffMember = User::query()
            ->where('club_id', $actor->club_id)
            ->findOrFail($user);

        Gate::authorize('manage', $staffMember);

        return new StaffResource($action->execute($actor, $staffMember, $request->toDto()));
    }

    public function destroy(Request $request, int $user, RemoveStaffAction $action): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        $staffMember = User::query()
            ->where('club_id', $actor->club_id)
            ->findOrFail($user);

        Gate::authorize('manage', $staffMember);

        $action->execute($actor, $staffMember);

        return response()->noContent();
    }
}
