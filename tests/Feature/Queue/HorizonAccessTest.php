<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

it('hides the Horizon dashboard from guests', function () {
    $this->get('/horizon')->assertForbidden();
});

it('hides the Horizon dashboard from a club user session', function () {
    $this->actingAs(User::factory()->create())->get('/horizon')->assertForbidden();
});

it('opens the Horizon gate only for an admin session', function () {
    expect(Gate::allows('viewHorizon'))->toBeFalse();

    $this->actingAs(Admin::factory()->create(), 'admin');

    expect(Gate::allows('viewHorizon'))->toBeTrue();
});
