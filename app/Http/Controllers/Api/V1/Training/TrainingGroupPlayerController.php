<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Domain\Player\Models\Player;
use App\Domain\Training\Actions\AddPlayerToTrainingGroupAction;
use App\Domain\Training\Actions\RemovePlayerFromTrainingGroupAction;
use App\Domain\Training\Models\TrainingGroup;
use App\Domain\Training\Models\TrainingGroupPlayer;
use App\Http\Requests\Training\AddTrainingGroupPlayerRequest;
use App\Http\Resources\TrainingGroupPlayerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TrainingGroupPlayerController
{
    public function index(Request $request, int $group): AnonymousResourceCollection
    {
        $model = TrainingGroup::query()->findOrFail($group);
        Gate::authorize('view', $model);

        $members = TrainingGroupPlayer::query()
            ->with('player')
            ->where('training_group_id', $model->id)
            ->get()
            ->sortBy(fn (TrainingGroupPlayer $member) => $member->player->name)
            ->values();

        return TrainingGroupPlayerResource::collection($members);
    }

    public function store(AddTrainingGroupPlayerRequest $request, int $group, AddPlayerToTrainingGroupAction $action): JsonResponse
    {
        $model = TrainingGroup::query()->findOrFail($group);
        $player = Player::query()->findOrFail($request->integer('player_id'));

        $membership = $action->execute($model, $player)->load('player');

        return (new TrainingGroupPlayerResource($membership))
            ->response()
            ->setStatusCode($membership->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, int $group, int $player, RemovePlayerFromTrainingGroupAction $action): Response
    {
        $model = TrainingGroup::query()->findOrFail($group);
        Gate::authorize('update', $model);

        $action->execute($model, $player);

        return response()->noContent();
    }
}
