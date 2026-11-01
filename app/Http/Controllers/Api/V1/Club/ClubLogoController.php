<?php

namespace App\Http\Controllers\Api\V1\Club;

use App\Domain\Club\Actions\RemoveClubLogoAction;
use App\Domain\Club\Actions\UpdateClubLogoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\UpdateClubLogoRequest;
use App\Http\Resources\ClubResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClubLogoController extends Controller
{
    public function store(UpdateClubLogoRequest $request, UpdateClubLogoAction $action): ClubResource
    {
        $club = $request->user()->club;

        Gate::authorize('update', $club);

        return new ClubResource($action->execute($club, $request->file('logo')));
    }

    public function destroy(Request $request, RemoveClubLogoAction $action): ClubResource
    {
        $club = $request->user()->club;

        Gate::authorize('update', $club);

        return new ClubResource($action->execute($club));
    }
}
