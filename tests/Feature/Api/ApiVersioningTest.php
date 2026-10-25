<?php

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

it('serves every API route under a version prefix', function () {
    $unversioned = collect(Route::getRoutes()->getRoutes())
        ->map(fn (LaravelRoute $route) => $route->uri())
        ->filter(fn (string $uri) => str_starts_with($uri, 'api/') && ! preg_match('#^api/v\d+/#', $uri))
        ->values()
        ->all();

    expect($unversioned)->toBe([]);
});
