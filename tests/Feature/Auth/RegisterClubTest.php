<?php

use App\Domain\Club\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

function registerPayload(array $overrides = []): array
{
    return array_merge([
        'club_name' => 'Talae El Zeitoun',
        'name' => 'Mahmoud',
        'email' => 'owner@example.com',
        'password' => 'password123',
    ], $overrides);
}

it('creates a club and its owner user and returns a token', function () {
    /** @var TestCase $this */
    $this->postJson('/api/v1/auth/register-club', registerPayload())
        ->assertCreated()
        ->assertJsonPath('data.email', 'owner@example.com')
        ->assertJsonPath('data.club.name', 'Talae El Zeitoun')
        ->assertJsonPath('data.club.slug', 'talae-el-zeitoun')
        ->assertJsonStructure(['meta' => ['token', 'abilities']]);

    $club = Club::where('slug', 'talae-el-zeitoun')->firstOrFail();

    expect(User::where('email', 'owner@example.com')->value('club_id'))->toBe($club->id);
});

it('returns a token that works on authenticated endpoints', function () {
    /** @var TestCase $this */
    $token = $this->postJson('/api/v1/auth/register-club', registerPayload())
        ->json('meta.token');

    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'owner@example.com');
});

it('generates a unique slug for clubs with the same name', function () {
    /** @var TestCase $this */
    $this->postJson('/api/v1/auth/register-club', registerPayload())->assertCreated();
    $this->postJson('/api/v1/auth/register-club', registerPayload(['email' => 'second@example.com']))
        ->assertCreated();

    $slugs = Club::pluck('slug');

    expect($slugs)->toHaveCount(2)
        ->and($slugs->unique())->toHaveCount(2);
});

it('generates a non-empty slug for an Arabic club name', function () {
    /** @var TestCase $this */
    $this->postJson('/api/v1/auth/register-club', registerPayload(['club_name' => 'نادي الزيتون']))
        ->assertCreated();

    expect(Club::firstOrFail()->slug)->not->toBeEmpty();
});

it('stores the request locale as the user locale', function () {
    /** @var TestCase $this */
    $this->postJson('/api/v1/auth/register-club', registerPayload(), ['Accept-Language' => 'ar'])
        ->assertCreated()
        ->assertJsonPath('data.locale', 'ar');
});

it('rejects a duplicate email without creating a club', function () {
    /** @var TestCase $this */
    User::factory()->create(['email' => 'owner@example.com']);
    $clubsBefore = Club::count();

    $this->postJson('/api/v1/auth/register-club', registerPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect(Club::count())->toBe($clubsBefore);
});

it('validates the payload', function () {
    /** @var TestCase $this */
    $this->postJson('/api/v1/auth/register-club', ['password' => 'short'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['club_name', 'name', 'email', 'password']);
});

it('does not allow choosing the club through the payload', function () {
    /** @var TestCase $this */
    $other = Club::factory()->create();

    $this->postJson('/api/v1/auth/register-club', registerPayload(['club_id' => $other->id]))
        ->assertCreated();

    expect(User::where('email', 'owner@example.com')->value('club_id'))->not->toBe($other->id);
});
