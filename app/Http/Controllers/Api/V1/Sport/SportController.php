<?php

namespace App\Http\Controllers\Api\V1\Sport;

use App\Domain\Sport\Cache\SportCatalog;
use App\Http\Resources\SportResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SportController
{
    public function index(Request $request, SportCatalog $catalog): AnonymousResourceCollection
    {
        Gate::authorize('view', $request->user()->club);

        return SportResource::collection($catalog->active());
    }
}
