<?php

namespace App\Http\Controllers\Api\V1\Club;

use App\Domain\Sport\Actions\AttachSportToClubAction;
use App\Domain\Sport\Actions\DetachSportFromClubAction;
use App\Domain\Sport\Cache\ClubSportList;
use App\Domain\Sport\DTOs\ClubSportView;
use App\Domain\Sport\Models\Sport;
use App\Http\Requests\Club\AttachClubSportRequest;
use App\Http\Resources\ClubSportResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ClubSportController
{
    public function index(Request $request, ClubSportList $list): AnonymousResourceCollection
    {
        Gate::authorize('view', $request->user()->club);

        return ClubSportResource::collection($list->forCurrentClub());
    }

    public function store(AttachClubSportRequest $request, AttachSportToClubAction $action): JsonResponse
    {
        $club = $request->user()->club;
        $sport = Sport::query()->findOrFail($request->integer('sport_id'));

        $clubSport = $action->execute($club, $sport)->load('sport');

        return (new ClubSportResource(ClubSportView::fromModel($clubSport)))
            ->response()
            ->setStatusCode($clubSport->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, int $sport, DetachSportFromClubAction $action): Response
    {
        $club = $request->user()->club;
        Gate::authorize('update', $club);

        $action->execute($club, $sport);

        return response()->noContent();
    }
}
