<?php

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

beforeEach(function () {
    Route::middleware(SetLocale::class)->get('/_test/locale', fn () => response()->json([
        'locale' => app()->getLocale(),
    ]));
});

it('falls back to the default locale', function () {
    /** @var TestCase $this */
    $this->getJson('/_test/locale', ['Accept-Language' => ''])
        ->assertOk()
        ->assertJson(['locale' => 'en'])
        ->assertHeader('Content-Language', 'en');
});

it('uses the Accept-Language header', function () {
    /** @var TestCase $this */
    $this->getJson('/_test/locale', ['Accept-Language' => 'ar'])
        ->assertJson(['locale' => 'ar'])
        ->assertHeader('Content-Language', 'ar');
});

it('handles regional codes and quality values', function () {
    /** @var TestCase $this */
    $this->getJson('/_test/locale', ['Accept-Language' => 'fr;q=0.9, ar-EG;q=0.8'])
        ->assertJson(['locale' => 'ar']);
});

it('uses the user locale when there is no header', function () {
    /** @var TestCase $this */
    // club_id is passed explicitly so the factory does not create a Club (no DB needed here).
    $user = User::factory()->make(['club_id' => 1, 'locale' => 'ar']);

    $this->actingAs($user)
        ->getJson('/_test/locale', ['Accept-Language' => ''])
        ->assertJson(['locale' => 'ar']);
});

it('prefers the header over the user locale', function () {
    /** @var TestCase $this */
    $user = User::factory()->make(['club_id' => 1, 'locale' => 'ar']);

    $this->actingAs($user)
        ->getJson('/_test/locale', ['Accept-Language' => 'en'])
        ->assertJson(['locale' => 'en']);
});

it('ignores unsupported locales', function () {
    /** @var TestCase $this */
    $user = User::factory()->make(['club_id' => 1, 'locale' => 'xx']);

    $this->actingAs($user)
        ->getJson('/_test/locale', ['Accept-Language' => 'fr'])
        ->assertJson(['locale' => 'en']);
});
