<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Domain\Player\Actions\CreatePlayerAction;
use App\Domain\Player\Actions\DeletePlayerAction;
use App\Domain\Player\Actions\ListPlayersAction;
use App\Domain\Player\Actions\UpdatePlayerAction;
use App\Domain\Player\Models\Player;
use App\Http\Controllers\Controller;
use App\Http\Requests\Player\ListPlayersRequest;
use App\Http\Requests\Player\StorePlayerRequest;
use App\Http\Requests\Player\UpdatePlayerRequest;
use App\Http\Resources\PlayerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PlayerController extends Controller
{
    public function index(ListPlayersRequest $request, ListPlayersAction $action): AnonymousResourceCollection
    {
        return PlayerResource::collection($action->execute($request->toData()));
    }

    public function store(StorePlayerRequest $request, CreatePlayerAction $action): JsonResponse
    {
        $user = $request->user();

        $player = $action->execute($user->club, $request->toData());

        return (new PlayerResource($player))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $player): PlayerResource
    {
        // Not implicit binding: the club scope needs CurrentClub, set by the tenant middleware.
        $model = Player::query()->findOrFail($player);

        Gate::authorize('view', $model);

        return new PlayerResource($model);
    }

    public function update(UpdatePlayerRequest $request, UpdatePlayerAction $action): PlayerResource
    {
        return new PlayerResource($action->execute($request->player(), $request->toData()));
    }

    public function destroy(Request $request, int $player, DeletePlayerAction $action): Response
    {
        $model = Player::query()->findOrFail($player);

        Gate::authorize('delete', $model);

        $action->execute($model);

        return response()->noContent();
    }
}
