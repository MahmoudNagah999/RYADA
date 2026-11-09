<?php

use App\Domain\Sport\Models\Sport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

it('lists the sports a club attached and caches them under the club key', function () {
    $sport = Sport::factory()->create(['slug' => 'karate']);
    $tenant = TenantFixture::create();

    $this->withToken($tenant->token)->postJson('/api/v1/club/sports', ['sport_id' => $sport->id])->assertCreated();

    $this->withToken($tenant->token)->getJson('/api/v1/club/sports')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'karate');

    expect(Cache::has("club:{$tenant->club->id}:sports"))->toBeTrue();
});

it('never shows the sports of one club to another club', function () {
    $sport = Sport::factory()->create(['slug' => 'karate']);
    $a = TenantFixture::create();
    $b = TenantFixture::create();

    $this->withToken($a->token)->postJson('/api/v1/club/sports', ['sport_id' => $sport->id])->assertCreated();
    $this->withToken($a->token)->getJson('/api/v1/club/sports')->assertJsonCount(1, 'data');

    app('auth')->forgetGuards();

    $this->withToken($b->token)->getJson('/api/v1/club/sports')->assertOk()->assertJsonCount(0, 'data');
});

it('serves the club sports from cache until they change', function () {
    $sport = Sport::factory()->create(['slug' => 'karate']);
    $tenant = TenantFixture::create();

    $this->withToken($tenant->token)->postJson('/api/v1/club/sports', ['sport_id' => $sport->id])->assertCreated();
    $this->withToken($tenant->token)->getJson('/api/v1/club/sports')->assertJsonCount(1, 'data');

    // A raw delete fires no model events, so only the cache can still answer.
    DB::table('club_sport')->delete();
    $this->withToken($tenant->token)->getJson('/api/v1/club/sports')->assertJsonCount(1, 'data');
});

it('refreshes the club sports after a detach', function () {
    $sport = Sport::factory()->create(['slug' => 'karate']);
    $tenant = TenantFixture::create();

    $this->withToken($tenant->token)->postJson('/api/v1/club/sports', ['sport_id' => $sport->id])->assertCreated();
    $this->withToken($tenant->token)->getJson('/api/v1/club/sports')->assertJsonCount(1, 'data');

    $this->withToken($tenant->token)->deleteJson("/api/v1/club/sports/{$sport->id}")->assertNoContent();

    $this->withToken($tenant->token)->getJson('/api/v1/club/sports')->assertJsonCount(0, 'data');
});

it('keeps the club entry valid when a sport is renamed', function () {
    $sport = Sport::factory()->create(['slug' => 'karate', 'name' => ['en' => 'Karate', 'ar' => 'كاراتيه']]);
    $tenant = TenantFixture::create();

    $this->withToken($tenant->token)->postJson('/api/v1/club/sports', ['sport_id' => $sport->id])->assertCreated();
    $this->withToken($tenant->token)->getJson('/api/v1/club/sports')->assertJsonPath('data.0.name', 'Karate');

    $sport->update(['name' => ['en' => 'Karate-Do', 'ar' => 'كاراتيه دو']]);

    $this->withToken($tenant->token)->getJson('/api/v1/club/sports')->assertJsonPath('data.0.name', 'Karate-Do');
});
