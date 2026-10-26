<?php

use App\Models\Admin;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the login page to guests and redirects the dashboard to it', function () {
    $this->get('/admin/login')->assertOk();

    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets an admin open the dashboard', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get('/admin')
        ->assertOk();
});

it('does not let a club user open the admin panel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect('/admin/login');
});

it('does not let a club api token open the admin panel', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->get('/admin')
        ->assertRedirect('/admin/login');
});

it('does not let an admin session use the club api', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'http.unauthenticated');
});

it('uses the separate admin guard in the panel', function () {
    expect(Filament::getPanel('admin')->getAuthGuard())->toBe('admin');
});
