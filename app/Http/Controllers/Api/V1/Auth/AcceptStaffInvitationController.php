<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Staff\Actions\AcceptStaffInvitationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptStaffInvitationRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class AcceptStaffInvitationController extends Controller
{
    public function store(
        AcceptStaffInvitationRequest $request,
        AcceptStaffInvitationAction $action,
    ): JsonResponse {
        $result = $action->execute($request->toDto());

        return (new UserResource($result->user->load('club')))
            ->additional(['meta' => [
                'token' => $result->token,
                'abilities' => $result->abilities,
            ]])
            ->response()
            ->setStatusCode(201);
    }
}
