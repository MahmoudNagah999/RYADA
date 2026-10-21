<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Club\Actions\RegisterClubAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\RegisterClubRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class RegisterClubController extends Controller
{
    public function __invoke(RegisterClubRequest $request, RegisterClubAction $action): JsonResponse
    {
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
