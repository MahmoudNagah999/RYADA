<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Actions\UpdateClubLogoAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['filesystems.club_logo_disk' => 'public']);
    Storage::fake('public');
});

it('uploads a logo under the club folder', function () {
    $fx = TenantFixture::create();

    $response = $this->withToken($fx->token)->post('/api/v1/club/logo', [
        'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
    ], ['Accept' => 'application/json']);

    $response->assertOk();
    expect($response->json('data.logo_url'))->not->toBeNull();

    $path = $fx->club->fresh()->logo_path;

    expect($path)->toStartWith("clubs/{$fx->club->id}/logo/");
    Storage::disk('public')->assertExists($path);
    expect(Activity::where('event', 'club.logo_updated')->count())->toBe(1);
});

it('rejects files that are not allowed images', function () {
    $fx = TenantFixture::create();

    $this->withToken($fx->token)->post('/api/v1/club/logo', [
        'logo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf'),
    ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('logo');

    expect($fx->club->fresh()->logo_path)->toBeNull();
});

it('forbids a coach from uploading a logo', function () {
    $fx = TenantFixture::create(RoleName::Coach);

    $this->withToken($fx->token)->post('/api/v1/club/logo', [
        'logo' => UploadedFile::fake()->image('logo.png'),
    ], ['Accept' => 'application/json'])
        ->assertForbidden();

    expect($fx->club->fresh()->logo_path)->toBeNull();
});

it('deletes the old file when the logo is replaced', function () {
    $fx = TenantFixture::create();
    $action = app(UpdateClubLogoAction::class);

    $action->execute($fx->club, UploadedFile::fake()->image('one.png'));
    $oldPath = $fx->club->logo_path;

    $action->execute($fx->club, UploadedFile::fake()->image('two.png'));

    expect($fx->club->logo_path)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($fx->club->logo_path);
});

it('removes the logo file and clears the path', function () {
    $fx = TenantFixture::create();
    app(UpdateClubLogoAction::class)->execute($fx->club, UploadedFile::fake()->image('one.png'));
    $path = $fx->club->logo_path;

    $this->withToken($fx->token)->deleteJson('/api/v1/club/logo')
        ->assertOk()
        ->assertJsonPath('data.logo_url', null);

    expect($fx->club->fresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
    expect(Activity::where('event', 'club.logo_removed')->count())->toBe(1);
});

it('does nothing and logs nothing when there is no logo to remove', function () {
    $fx = TenantFixture::create();

    $this->withToken($fx->token)->deleteJson('/api/v1/club/logo')->assertOk();

    expect(Activity::where('event', 'club.logo_removed')->count())->toBe(0);
});
