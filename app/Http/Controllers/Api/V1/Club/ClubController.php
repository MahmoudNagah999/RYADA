<?php

namespace App\Http\Controllers\Api\V1\Club;

use App\Domain\Club\Actions\UpdateClubProfileAction;
use App\Domain\Club\DTOs\UpdateClubProfileData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\UpdateClubProfileRequest;
use App\Http\Resources\ClubResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClubController extends Controller
{
    public function show(Request $request): ClubResource
    {
        $club = $request->user()->club;

        Gate::authorize('view', $club);

        return new ClubResource($club);
    }

    public function update(UpdateClubProfileRequest $request, UpdateClubProfileAction $action): ClubResource
    {
        $club = $request->user()->club;

        Gate::authorize('update', $club);

        $club = $action->execute($club, UpdateClubProfileData::fromArray($request->validated()));

        return new ClubResource($club);
    }
}
