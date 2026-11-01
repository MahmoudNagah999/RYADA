<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Club\Actions\UpdateClubProfileAction;
use App\Domain\Club\DTOs\UpdateClubProfileData;
use App\Domain\Club\Models\Club;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('logs only the fields that changed, with from and to', function () {
    $club = Club::factory()->create([
        'name' => 'Old name',
        'contact_email' => null,
        'contact_phone' => null,
        'city' => 'Cairo',
        'address' => null,
    ]);

    app(UpdateClubProfileAction::class)->execute($club, new UpdateClubProfileData(
        name: 'New name',
        contactEmail: null,
        contactPhone: null,
        city: 'Cairo',
        address: null,
    ));

    $changes = Activity::where('event', 'club.profile_updated')->sole()
        ->properties->get('changes');

    expect($changes)->toEqual([
        'name' => ['from' => 'Old name', 'to' => 'New name'],
    ]);
});

it('does not log anything when nothing changed', function () {
    $club = Club::factory()->create([
        'name' => 'Same',
        'contact_email' => null,
        'contact_phone' => null,
        'city' => null,
        'address' => null,
    ]);

    app(UpdateClubProfileAction::class)->execute($club, new UpdateClubProfileData(
        name: 'Same',
        contactEmail: null,
        contactPhone: null,
        city: null,
        address: null,
    ));

    expect(Activity::where('event', 'club.profile_updated')->count())->toBe(0);
});

it('clears optional fields that are sent as null (PUT replaces the profile)', function () {
    $club = Club::factory()->create(['name' => 'Club', 'city' => 'Giza']);

    app(UpdateClubProfileAction::class)->execute($club, new UpdateClubProfileData(
        name: 'Club',
        contactEmail: null,
        contactPhone: null,
        city: null,
        address: null,
    ));

    expect($club->fresh()->city)->toBeNull();
});
