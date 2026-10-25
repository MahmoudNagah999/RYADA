<?php

use App\Domain\Auth\Enums\RoleName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\Support\Tenancy\TenantFixture;
use Tests\TestCase;

uses(RefreshDatabase::class);

it('returns 401 with a code and a translated message', function () {
    /** @var TestCase $this */
    $this->getJson('/api/v1/auth/me', ['Accept-Language' => 'ar'])
        ->assertUnauthorized()
        ->assertExactJson([
            'code' => 'http.unauthenticated',
            'message' => trans('errors.http.unauthenticated', [], 'ar'),
        ]);
});

it('returns 403 when a policy denies the action', function () {
    /** @var TestCase $this */
    $a = TenantFixture::create(RoleName::Owner);
    $b = TenantFixture::create(RoleName::Owner);

    Route::middleware(['api', 'auth:sanctum', 'tenant'])
        ->get('/api/v1/_forbidden', function () use ($b) {
            Gate::authorize('update', $b->club);
        });

    $this->withToken($a->token)->getJson('/api/v1/_forbidden')
        ->assertForbidden()
        ->assertExactJson([
            'code' => 'http.forbidden',
            'message' => trans('errors.http.forbidden', [], 'en'),
        ]);
});

it('returns 404 for an unknown URL', function () {
    /** @var TestCase $this */
    $this->getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertExactJson([
            'code' => 'http.not_found',
            'message' => trans('errors.http.not_found', [], 'en'),
        ]);
});

it('returns 405 with an Allow header for a wrong method', function () {
    /** @var TestCase $this */
    $this->postJson('/api/v1/auth/me')
        ->assertStatus(405)
        ->assertJsonPath('code', 'http.method_not_allowed')
        ->assertHeader('Allow');
});

it('returns 422 with a code and field errors', function () {
    /** @var TestCase $this */
    $this->postJson('/api/v1/auth/register-club', ['password' => 'short'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation.failed')
        ->assertJsonStructure(['code', 'message', 'errors' => ['club_name', 'name', 'email', 'password']]);
});

it('returns 429 with a Retry-After header when throttled', function () {
    /** @var TestCase $this */
    Route::middleware(['api', 'throttle:1,1'])
        ->get('/api/v1/_throttled', fn () => response()->json(['ok' => true]));

    $this->getJson('/api/v1/_throttled')->assertOk();

    $response = $this->getJson('/api/v1/_throttled')
        ->assertStatus(429)
        ->assertJsonPath('code', 'http.too_many_requests')
        ->assertHeader('Retry-After');

    expect($response->json('message'))->not->toContain(':seconds');
});

it('hides internal details on a server error', function () {
    /** @var TestCase $this */
    config(['app.debug' => false]);

    Route::middleware('api')->get('/api/v1/_boom', function () {
        throw new RuntimeException('secret-internal-detail');
    });

    $this->getJson('/api/v1/_boom')
        ->assertStatus(500)
        ->assertExactJson([
            'code' => 'server.error',
            'message' => trans('errors.server.error', [], 'en'),
        ])
        ->assertDontSee('secret-internal-detail');
});

it('translates 404 and 405 responses too', function () {
    /** @var TestCase $this */
    $this->getJson('/api/v1/does-not-exist', ['Accept-Language' => 'ar'])
        ->assertNotFound()
        ->assertJsonPath('message', trans('errors.http.not_found', [], 'ar'));

    $this->postJson('/api/v1/auth/me', [], ['Accept-Language' => 'ar'])
        ->assertStatus(405)
        ->assertJsonPath('message', trans('errors.http.method_not_allowed', [], 'ar'));
});
