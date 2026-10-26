<?php

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Actions\CreateClubAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('creates a club with a slug and its four roles', function () {
    $club = app(CreateClubAction::class)->execute('Zeitoun Karate Club');

    $roles = Role::query()->where('club_id', $club->id)->pluck('name')->sort()->values()->all();
    $expected = collect(RoleName::cases())->map(fn (RoleName $r) => $r->value)->sort()->values()->all();

    expect($club->slug)->toBe('zeitoun-karate-club')
        ->and($roles)->toBe($expected);
});

it('generates a unique slug for duplicate names', function () {
    $first = app(CreateClubAction::class)->execute('Zeitoun');
    $second = app(CreateClubAction::class)->execute('Zeitoun');

    expect($first->slug)->toBe('zeitoun')
        ->and($second->slug)->not->toBe($first->slug)
        ->and($second->slug)->toStartWith('zeitoun-');
});

it('falls back to a default slug when the name has nothing sluggable', function () {
    $club = app(CreateClubAction::class)->execute('!!!');

    expect($club->slug)->toBe('club');
});
