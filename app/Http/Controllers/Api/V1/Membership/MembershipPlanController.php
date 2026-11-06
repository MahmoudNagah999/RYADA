<?php

namespace App\Http\Controllers\Api\V1\Membership;

use App\Domain\Membership\Actions\CreateMembershipPlanAction;
use App\Domain\Membership\Actions\ListMembershipPlansAction;
use App\Domain\Membership\Actions\UpdateMembershipPlanAction;
use App\Domain\Membership\DTOs\CreateMembershipPlanData;
use App\Domain\Membership\DTOs\UpdateMembershipPlanData;
use App\Domain\Membership\Models\MembershipPlan;
use App\Http\Requests\Membership\ListMembershipPlansRequest;
use App\Http\Requests\Membership\StoreMembershipPlanRequest;
use App\Http\Requests\Membership\UpdateMembershipPlanRequest;
use App\Http\Resources\MembershipPlanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MembershipPlanController
{
    public function index(ListMembershipPlansRequest $request, ListMembershipPlansAction $action): AnonymousResourceCollection
    {
        $plans = $action->execute(
            $request->has('sport_id') ? $request->integer('sport_id') : null,
            $request->has('is_active') ? $request->boolean('is_active') : null,
        );

        return MembershipPlanResource::collection($plans);
    }

    public function store(StoreMembershipPlanRequest $request, CreateMembershipPlanAction $action): JsonResponse
    {
        $plan = $action->execute(new CreateMembershipPlanData(
            sportId: $request->integer('sport_id'),
            name: $request->string('name')->toString(),
            priceCents: $request->integer('price_cents'),
            durationMonths: $request->integer('duration_months'),
            isActive: $request->has('is_active') ? $request->boolean('is_active') : true,
        ));

        return (new MembershipPlanResource($plan->load('sport')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $plan): MembershipPlanResource
    {
        $model = MembershipPlan::query()->with('sport')->findOrFail($plan);
        Gate::authorize('view', $model);

        return new MembershipPlanResource($model);
    }

    public function update(UpdateMembershipPlanRequest $request, int $plan, UpdateMembershipPlanAction $action): MembershipPlanResource
    {
        $model = MembershipPlan::query()->findOrFail($plan);

        $updated = $action->execute($model, new UpdateMembershipPlanData(
            name: $request->string('name')->toString(),
            priceCents: $request->integer('price_cents'),
            durationMonths: $request->integer('duration_months'),
            isActive: $request->boolean('is_active'),
        ));

        return new MembershipPlanResource($updated->load('sport'));
    }
}
