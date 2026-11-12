<?php

use App\Domain\Club\Models\Club;
use App\Domain\Shared\Cache\TenantCache;
use App\Domain\Shared\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

it('refuses to build a key without a current club', function () {
    app(TenantCache::class)->key('sports');
})->throws(LogicException::class);

it('puts the club id in the key', function () {
    $club = Club::factory()->create();

    $key = app(CurrentClub::class)->runAs($club, fn () => app(TenantCache::class)->key('sports'));

    expect($key)->toBe("club:{$club->id}:sports");
});

it('keeps the same cache name separate between two clubs', function () {
    $a = Club::factory()->create();
    $b = Club::factory()->create();
    $current = app(CurrentClub::class);

    $current->runAs($a, fn () => app(TenantCache::class)->remember('sports', 60, fn () => 'data-of-a'));
    $forB = $current->runAs($b, fn () => app(TenantCache::class)->remember('sports', 60, fn () => 'data-of-b'));
    $forA = $current->runAs($a, fn () => app(TenantCache::class)->remember('sports', 60, fn () => 'should-not-run'));

    expect($forB)->toBe('data-of-b')
        ->and($forA)->toBe('data-of-a');
});

it('runs the callback only once while the value is cached', function () {
    $club = Club::factory()->create();
    $calls = 0;

    $read = function () use ($club, &$calls) {
        return app(CurrentClub::class)->runAs($club, function () use (&$calls) {
            return app(TenantCache::class)->remember('sports', 60, function () use (&$calls) {
                $calls++;

                return 'value';
            });
        });
    };

    $read();
    $read();

    expect($calls)->toBe(1);
});

it('forgets only the entry of the given club', function () {
    $a = Club::factory()->create();
    $b = Club::factory()->create();
    $current = app(CurrentClub::class);

    $current->runAs($a, fn () => app(TenantCache::class)->remember('sports', 60, fn () => 'a'));
    $current->runAs($b, fn () => app(TenantCache::class)->remember('sports', 60, fn () => 'b'));

    app(TenantCache::class)->forgetForClub($a->id, 'sports');

    $afterA = $current->runAs($a, fn () => app(TenantCache::class)->remember('sports', 60, fn () => 'a-reloaded'));
    $afterB = $current->runAs($b, fn () => app(TenantCache::class)->remember('sports', 60, fn () => 'should-not-run'));

    expect($afterA)->toBe('a-reloaded')
        ->and($afterB)->toBe('b');
});
