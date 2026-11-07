<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Domain\Training\Actions\CreateTrainingGroupAction;
use App\Domain\Training\Actions\ListTrainingGroupsAction;
use App\Domain\Training\Actions\UpdateTrainingGroupAction;
use App\Domain\Training\DTOs\CreateTrainingGroupData;
use App\Domain\Training\DTOs\UpdateTrainingGroupData;
use App\Domain\Training\Models\TrainingGroup;
use App\Http\Requests\Training\ListTrainingGroupsRequest;
use App\Http\Requests\Training\StoreTrainingGroupRequest;
use App\Http\Requests\Training\UpdateTrainingGroupRequest;
use App\Http\Resources\TrainingGroupResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TrainingGroupController
{
    public function index(ListTrainingGroupsRequest $request, ListTrainingGroupsAction $action): AnonymousResourceCollection
    {
        $groups = $action->execute(
            $request->has('sport_id') ? $request->integer('sport_id') : null,
            $request->has('is_active') ? $request->boolean('is_active') : null,
        );

        return TrainingGroupResource::collection($groups);
    }

    public function store(StoreTrainingGroupRequest $request, CreateTrainingGroupAction $action): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $group = $action->execute(new CreateTrainingGroupData(
            clubId: $user->club_id,
            sportId: $request->integer('sport_id'),
            name: $request->string('name')->toString(),
            coachId: $request->coachId(),
            schedules: $request->scheduleSlots(),
            isActive: $request->has('is_active') ? $request->boolean('is_active') : true,
        ));

        return (new TrainingGroupResource($group->load(['sport', 'coach', 'schedules'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $group): TrainingGroupResource
    {
        $model = TrainingGroup::query()->with(['sport', 'coach', 'schedules'])->findOrFail($group);
        Gate::authorize('view', $model);

        return new TrainingGroupResource($model);
    }

    public function update(UpdateTrainingGroupRequest $request, int $group, UpdateTrainingGroupAction $action): TrainingGroupResource
    {
        $model = TrainingGroup::query()->with('schedules')->findOrFail($group);

        $updated = $action->execute($model, new UpdateTrainingGroupData(
            name: $request->string('name')->toString(),
            coachId: $request->coachId(),
            isActive: $request->boolean('is_active'),
            schedules: $request->scheduleSlots(),
        ));

        return new TrainingGroupResource($updated->load(['sport', 'coach', 'schedules']));
    }
}
