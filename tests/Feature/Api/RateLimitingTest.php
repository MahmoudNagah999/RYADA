<?php

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(RefreshDatabase::class);

function throttledLogin(TestCase $test, string $email)
{
    return $test->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'wrong-password']);
}

it('throttles login attempts per email and IP', function () {
    /** @var TestCase $this */
    for ($i = 0; $i < 5; $i++) {
        throttledLogin($this, 'nobody@example.com')->assertUnauthorized();
    }

    throttledLogin($this, 'nobody@example.com')
        ->assertStatus(429)
        ->assertJsonPath('code', 'http.too_many_requests')
        ->assertHeader('Retry-After');

    // A different email from the same IP is still allowed.
    throttledLogin($this, 'someone@example.com')->assertUnauthorized();
});

it('throttles club registration per IP', function () {
    /** @var TestCase $this */
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/auth/register-club', ['password' => 'short'])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/register-club', ['password' => 'short'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'http.too_many_requests');
});

it('limits the general API per user, and per IP for guests', function () {
    $limiter = RateLimiter::limiter('api');

    $guest = $limiter(Request::create('/api/v1/auth/me', 'GET', server: ['REMOTE_ADDR' => '10.0.0.1']));

    $asUser = function (int $id) use ($limiter): Limit {
        $user = User::factory()->make(['club_id' => 1])->forceFill(['id' => $id]);
        $request = Request::create('/api/v1/auth/me', 'GET');
        $request->setUserResolver(fn () => $user);

        return $limiter($request);
    };

    $first = $asUser(1);
    $second = $asUser(2);

    expect($guest->maxAttempts)->toBe(60)
        ->and($guest->key)->toContain('10.0.0.1')
        ->and($first->maxAttempts)->toBe(120)
        ->and($first->key)->not->toBe($second->key);
});

it('applies the general throttle to every API route', function () {
    $missing = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route) => str_starts_with($route->uri(), 'api/'))
        ->reject(fn (LaravelRoute $route) => in_array('throttle:api', $route->gatherMiddleware(), true))
        ->map(fn (LaravelRoute $route) => $route->uri())
        ->values()
        ->all();

    expect($missing)->toBe([]);
});
