<?php

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

/**
 * Routes that are deliberately outside the tenant boundary.
 * Adding a route here needs a reason in the PR.
 */
const TENANT_PUBLIC_ROUTES = [
    'POST api/v1/auth/login',
    'POST api/v1/auth/register-club',
];

// Any valid token may call these, whatever its club.
const TENANT_AUTH_ONLY_ROUTES = [
    'POST api/v1/auth/logout',
];

/** @return array<string, LaravelRoute> keyed by "METHOD uri" */
function tenantApiRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route) => str_starts_with($route->uri(), 'api/'))
        ->mapWithKeys(fn (LaravelRoute $route) => [$route->methods()[0].' '.$route->uri() => $route])
        ->all();
}

function tenantRouteIsProtected(LaravelRoute $route): bool
{
    $middleware = array_values($route->gatherMiddleware());
    $auth = array_search('auth:sanctum', $middleware, true);
    $tenant = array_search('tenant', $middleware, true);

    // `tenant` must come after authentication, because it reads the authenticated user.
    return $auth !== false && $tenant !== false && $auth < $tenant;
}

it('puts every club-scoped API route behind auth:sanctum and then tenant', function () {
    $open = array_merge(TENANT_PUBLIC_ROUTES, TENANT_AUTH_ONLY_ROUTES);

    $unprotected = collect(tenantApiRoutes())
        ->except($open)
        ->reject(fn (LaravelRoute $route) => tenantRouteIsProtected($route))
        ->keys()
        ->all();

    expect($unprotected)->toBe([]);
});

it('keeps the allowlists in sync with the real routes', function () {
    $open = array_merge(TENANT_PUBLIC_ROUTES, TENANT_AUTH_ONLY_ROUTES);

    expect(array_values(array_diff($open, array_keys(tenantApiRoutes()))))->toBe([]);
});

it('does not put the tenant middleware on public routes', function () {
    $routes = tenantApiRoutes();

    foreach (TENANT_PUBLIC_ROUTES as $key) {
        expect($routes[$key]->gatherMiddleware())->not->toContain('tenant');
    }
});
