<?php

use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

beforeEach(function () {
    Route::middleware(SetLocale::class)->get('/_test/limit', function () {
        throw new BusinessRuleException('player.limit_reached', ['max' => 50]);
    });
});

it('returns a stable code and an english message', function () {
    /** @var TestCase $this */
    $this->getJson('/_test/limit', ['Accept-Language' => 'en'])
        ->assertStatus(422)
        ->assertExactJson([
            'code' => 'player.limit_reached',
            'message' => 'Your plan allows a maximum of 50 players.',
        ]);
});

it('returns the same code with an arabic message', function () {
    /** @var TestCase $this */
    $this->getJson('/_test/limit', ['Accept-Language' => 'ar'])
        ->assertStatus(422)
        ->assertExactJson([
            'code' => 'player.limit_reached',
            'message' => trans('errors.player.limit_reached', ['max' => 50], 'ar'),
        ]);
});

it('keeps the code identical across locales', function () {
    /** @var TestCase $this */
    $en = $this->getJson('/_test/limit', ['Accept-Language' => 'en'])->json();
    $ar = $this->getJson('/_test/limit', ['Accept-Language' => 'ar'])->json();

    expect($en['code'])->toBe($ar['code'])
        ->and($en['message'])->not->toBe($ar['message']);
});
