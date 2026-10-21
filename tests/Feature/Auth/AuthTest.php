<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(RefreshDatabase::class);

it('logs in with valid credentials and returns a token', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['email' => 'coach@example.com']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'coach@example.com',
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'locale', 'club' => ['id', 'name', 'slug']],
            'meta' => ['token'],
        ]);

    expect($user->tokens()->count())->toBe(1);
});

it('rejects a wrong password with a stable error code', function () {
    /** @var TestCase $this */
    User::factory()->create(['email' => 'coach@example.com']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'coach@example.com',
        'password' => 'wrong',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'auth.invalid_credentials');
});

it('rejects an unknown email with the same error', function () {
    /** @var TestCase $this */
    $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'password',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'auth.invalid_credentials');
});

it('validates the login payload', function () {
    /** @var TestCase $this */
    $this->postJson('/api/v1/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('returns the authenticated user with their club', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.club.id', $user->club_id);
});

it('requires authentication for me', function () {
    /** @var TestCase $this */
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('revokes the current token on logout', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('uses the user locale for token-authenticated requests', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['locale' => 'ar']);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/auth/me', ['Accept-Language' => ''])
        ->assertOk()
        ->assertHeader('Content-Language', 'ar');
});

it('issues a full-access token by default', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['email' => 'coach@example.com']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'coach@example.com',
        'password' => 'password',
    ])->assertJsonPath('meta.abilities', ['*']);

    expect($user->tokens()->first()->abilities)->toBe(['*']);
});

it('issues a token limited to the requested abilities', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['email' => 'coach@example.com']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'coach@example.com',
        'password' => 'password',
        'abilities' => ['read'],
    ])->assertOk()->assertJsonPath('meta.abilities', ['read']);

    expect($user->tokens()->first()->abilities)->toBe(['read']);
});

it('rejects unknown abilities', function () {
    /** @var TestCase $this */
    User::factory()->create(['email' => 'coach@example.com']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'coach@example.com',
        'password' => 'password',
        'abilities' => ['*'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['abilities.0']);
});

it('lets a read-only token read but not write', function () {
    /** @var TestCase $this */
    Route::middleware(['api', 'auth:sanctum', 'abilities:write'])
        ->post('/api/_test/write', fn () => response()->noContent());

    $user = User::factory()->create();
    $readToken = $user->createToken('ro', ['read'])->plainTextToken;
    $writeToken = $user->createToken('rw', ['write'])->plainTextToken;

    $this->withToken($readToken)->getJson('/api/v1/auth/me')->assertOk();

    // Guards cache the resolved user inside a single test; reset between requests.
    $this->app['auth']->forgetGuards();
    $this->withToken($readToken)->postJson('/api/_test/write')->assertForbidden();

    $this->app['auth']->forgetGuards();
    $this->withToken($writeToken)->postJson('/api/_test/write')->assertNoContent();
});
