<?php

use App\Domain\Sport\Cache\SportCatalog;
use App\Domain\Sport\Models\Sport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

it('serves every locale from one catalog entry', function () {
    Sport::factory()->create(['slug' => 'karate', 'name' => ['en' => 'Karate', 'ar' => 'كاراتيه']]);
    $tenant = TenantFixture::create();

    $en = $this->withToken($tenant->token)->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/sports');
    $ar = $this->withToken($tenant->token)->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v1/sports');

    expect($en->json('data.0.name'))->toBe('Karate')
        ->and($ar->json('data.0.name'))->toBe('كاراتيه')
        ->and(Cache::has(SportCatalog::KEY))->toBeTrue();
});

it('serves the catalog from cache until a sport changes', function () {
    Sport::factory()->create(['slug' => 'karate']);
    $tenant = TenantFixture::create();

    $this->withToken($tenant->token)->getJson('/api/v1/sports')->assertJsonCount(1, 'data');

    // A raw delete fires no model events, so only the cache can still answer.
    DB::table('sports')->delete();
    $this->withToken($tenant->token)->getJson('/api/v1/sports')->assertJsonCount(1, 'data');

    Sport::factory()->create(['slug' => 'football']);

    $this->withToken($tenant->token)->getJson('/api/v1/sports')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'football');
});

it('hides a sport from the catalog as soon as it is deactivated', function () {
    $sport = Sport::factory()->create(['slug' => 'karate']);
    $tenant = TenantFixture::create();

    $this->withToken($tenant->token)->getJson('/api/v1/sports')->assertJsonCount(1, 'data');

    $sport->update(['is_active' => false]);

    $this->withToken($tenant->token)->getJson('/api/v1/sports')->assertJsonCount(0, 'data');
});

it('keeps the cache when the transaction that changed a sport rolls back', function () {
    Sport::factory()->create(['slug' => 'karate']);
    app(SportCatalog::class)->all();

    try {
        DB::transaction(function () {
            Sport::factory()->create(['slug' => 'football']);

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect(Cache::has(SportCatalog::KEY))->toBeTrue();
});
