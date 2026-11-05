<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Domain\Player\Models\Player;
use App\Domain\Sport\Actions\EnrollPlayerInSportAction;
use App\Domain\Sport\Actions\UnenrollPlayerFromSportAction;
use App\Domain\Sport\Models\PlayerSport;
use App\Domain\Sport\Models\Sport;
use App\Http\Requests\Player\EnrollPlayerSportRequest;
use App\Http\Resources\PlayerSportResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PlayerSportController
{
    public function index(Request $request, int $player): AnonymousResourceCollection
    {
        $model = Player::query()->findOrFail($player);
        Gate::authorize('view', $model);

        $enrollments = PlayerSport::query()
            ->with('sport')
            ->where('player_id', $model->id)
            ->get()
            ->sortBy(fn (PlayerSport $enrollment) => $enrollment->sport->slug)
            ->values();

        return PlayerSportResource::collection($enrollments);
    }

    public function store(EnrollPlayerSportRequest $request, int $player, EnrollPlayerInSportAction $action): JsonResponse
    {
        $model = Player::query()->findOrFail($player);
        $sport = Sport::query()->findOrFail($request->integer('sport_id'));

        $enrollment = $action->execute($model, $sport)->load('sport');

        return (new PlayerSportResource($enrollment))
            ->response()
            ->setStatusCode($enrollment->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, int $player, int $sport, UnenrollPlayerFromSportAction $action): Response
    {
        $model = Player::query()->findOrFail($player);
        Gate::authorize('update', $model);

        $action->execute($model, $sport);

        return response()->noContent();
    }
}
