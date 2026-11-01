<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Enums\ClubStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

function profilePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Talae El Zeitoun',
        'contact_email' => 'info@club.test',
        'contact_phone' => '+201001234567',
        'city' => 'Cairo',
        'address' => '12 Zeitoun St',
    ], $overrides);
}

it('returns the profile of the user\'s own club only', function () {
    $a = TenantFixture::create();
    $b = TenantFixture::create();

    $response = $this->withToken($b->token)->getJson('/api/v1/club');

    $response->assertOk()
        ->assertJsonPath('data.id', $b->club->id)
        ->assertJsonPath('data.logo_url', null);

    expect($response->json('data.id'))->not->toBe($a->club->id);
});

it('lets a coach read the profile', function () {
    $fx = TenantFixture::create(RoleName::Coach);

    $this->withToken($fx->token)->getJson('/api/v1/club')
        ->assertOk()
        ->assertJsonPath('data.id', $fx->club->id);
});

it('lets the owner update the profile without touching the slug', function () {
    $fx = TenantFixture::create();
    $slug = $fx->club->slug;

    $this->withToken($fx->token)
        ->putJson('/api/v1/club', profilePayload(['slug' => 'hacked']))
        ->assertOk()
        ->assertJsonPath('data.name', 'Talae El Zeitoun')
        ->assertJsonPath('data.city', 'Cairo');

    $club = $fx->club->fresh();

    expect($club->slug)->toBe($slug)
        ->and($club->contact_phone)->toBe('+201001234567');
});

it('records an audit entry when the profile changes', function () {
    $fx = TenantFixture::create();

    $this->withToken($fx->token)->putJson('/api/v1/club', profilePayload())->assertOk();

    $activity = Activity::where('event', 'club.profile_updated')->sole();

    expect($activity->club_id)->toBe($fx->club->id);
});

it('forbids a coach from updating the profile', function () {
    $fx = TenantFixture::create(RoleName::Coach);

    $this->withToken($fx->token)->putJson('/api/v1/club', profilePayload())
        ->assertForbidden()
        ->assertJsonPath('code', 'http.forbidden');

    expect($fx->club->fresh()->city)->not->toBe('Cairo');
});

it('requires a name', function () {
    $fx = TenantFixture::create();

    $this->withToken($fx->token)
        ->putJson('/api/v1/club', profilePayload(['name' => '']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation.failed')
        ->assertJsonValidationErrors('name');
});

it('rejects an invalid phone number', function () {
    $fx = TenantFixture::create();

    $this->withToken($fx->token)
        ->putJson('/api/v1/club', profilePayload(['contact_phone' => '12 34-56']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('contact_phone');
});

it('blocks a suspended club from the profile endpoints', function () {
    $fx = TenantFixture::create();
    $fx->club->forceFill(['status' => ClubStatus::Suspended])->save();

    $this->withToken($fx->token)->getJson('/api/v1/club')
        ->assertForbidden()
        ->assertJsonPath('code', 'club.suspended');
});
