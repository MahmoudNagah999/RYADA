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

// Club-scoped, but reachable by a suspended or expired club (clients need to read the status).
// Adding a route here needs a reason in the PR.
const SUBSCRIPTION_EXEMPT_ROUTES = [
    'GET api/v1/auth/me',
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

function subscriptionRouteIsEnforced(LaravelRoute $route): bool
{
    $middleware = array_values($route->gatherMiddleware());
    $tenant = array_search('tenant', $middleware, true);
    $subscription = array_search('subscription', $middleware, true);

    // `subscription` reads the user's club, so it must run after `tenant`.
    return $tenant !== false && $subscription !== false && $tenant < $subscription;
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
    $open = array_merge(TENANT_PUBLIC_ROUTES, TENANT_AUTH_ONLY_ROUTES, SUBSCRIPTION_EXEMPT_ROUTES);

    expect(array_values(array_diff($open, array_keys(tenantApiRoutes()))))->toBe([]);
});

it('does not put the tenant middleware on public routes', function () {
    $routes = tenantApiRoutes();

    foreach (TENANT_PUBLIC_ROUTES as $key) {
        expect($routes[$key]->gatherMiddleware())->not->toContain('tenant');
    }
});

it('enforces the club subscription on every club-scoped route unless exempt', function () {
    $open = array_merge(TENANT_PUBLIC_ROUTES, TENANT_AUTH_ONLY_ROUTES, SUBSCRIPTION_EXEMPT_ROUTES);

    $unenforced = collect(tenantApiRoutes())
        ->except($open)
        ->reject(fn (LaravelRoute $route) => subscriptionRouteIsEnforced($route))
        ->keys()
        ->all();

    expect($unenforced)->toBe([]);
});

it('never blocks public or auth-only routes with the subscription middleware', function () {
    $routes = tenantApiRoutes();

    foreach (array_merge(TENANT_PUBLIC_ROUTES, TENANT_AUTH_ONLY_ROUTES) as $key) {
        expect($routes[$key]->gatherMiddleware())->not->toContain('subscription');
    }
});
