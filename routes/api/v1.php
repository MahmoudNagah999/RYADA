<?php

use App\Http\Controllers\Api\V1\Auth\AcceptStaffInvitationController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\RegisterClubController;
use App\Http\Controllers\Api\V1\Club\ClubController;
use App\Http\Controllers\Api\V1\Club\ClubLogoController;
use App\Http\Controllers\Api\V1\Club\ClubSportController;
use App\Http\Controllers\Api\V1\Player\PlayerController;
use App\Http\Controllers\Api\V1\Player\PlayerSportController;
use App\Http\Controllers\Api\V1\Sport\SportController;
use App\Http\Controllers\Api\V1\Staff\StaffController;
use App\Http\Controllers\Api\V1\Staff\StaffInvitationController;
use Illuminate\Support\Facades\Route;

// Convention: read routes use `ability:read,write` (any of); write routes use `abilities:write`.
// Convention: every club-scoped route goes inside the `tenant` group (after auth:sanctum),
// and inside the nested `subscription` group, which blocks suspended and expired clubs.
// Convention: public endpoints that can be abused get their own limiter (`throttle:<name>`).

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('register-club', RegisterClubController::class)->middleware('throttle:register');
    Route::post('accept-invitation', [AcceptStaffInvitationController::class, 'store'])->middleware('throttle:invitation');

    Route::middleware('auth:sanctum')->group(function () {
        // Logout is allowed for any valid token, whatever its abilities or club.
        Route::post('logout', [AuthController::class, 'logout']);

        Route::middleware('tenant')->group(function () {
            // Outside `subscription` on purpose: a suspended or expired club must still be able
            // to read its own status. A new exception needs an entry in RouteTenancyTest.
            Route::get('me', [AuthController::class, 'me'])->middleware('ability:read,write');
        });
    });
});

Route::middleware(['auth:sanctum', 'tenant', 'subscription'])->group(function () {
    Route::get('club', [ClubController::class, 'show'])->middleware('ability:read,write');
    Route::put('club', [ClubController::class, 'update'])->middleware('abilities:write');
    Route::post('club/logo', [ClubLogoController::class, 'store'])->middleware('abilities:write');
    Route::delete('club/logo', [ClubLogoController::class, 'destroy'])->middleware('abilities:write');

    Route::get('staff', [StaffController::class, 'index'])->middleware('ability:read,write');
    Route::patch('staff/{user}/role', [StaffController::class, 'updateRole'])
        ->whereNumber('user')
        ->middleware('abilities:write');
    Route::delete('staff/{user}', [StaffController::class, 'destroy'])
        ->whereNumber('user')
        ->middleware('abilities:write');

    Route::get('staff/invitations', [StaffInvitationController::class, 'index'])->middleware('ability:read,write');
    Route::post('staff/invitations', [StaffInvitationController::class, 'store'])->middleware('abilities:write');
    Route::delete('staff/invitations/{invitation}', [StaffInvitationController::class, 'destroy'])
        ->middleware('abilities:write');

    Route::post('players', [PlayerController::class, 'store'])->middleware('abilities:write');
    Route::get('players/{player}', [PlayerController::class, 'show'])
        ->whereNumber('player')
        ->middleware('ability:read,write');
    Route::get('players', [PlayerController::class, 'index'])->middleware('ability:read,write');
    Route::put('players/{player}', [PlayerController::class, 'update'])
        ->whereNumber('player')
        ->middleware('abilities:write');
    Route::delete('players/{player}', [PlayerController::class, 'destroy'])
        ->whereNumber('player')
        ->middleware('abilities:write');

    Route::get('players/{player}/sports', [PlayerSportController::class, 'index'])
        ->whereNumber('player')
        ->middleware('ability:read,write');
    Route::post('players/{player}/sports', [PlayerSportController::class, 'store'])
        ->whereNumber('player')
        ->middleware('abilities:write');
    Route::delete('players/{player}/sports/{sport}', [PlayerSportController::class, 'destroy'])
        ->whereNumber(['player', 'sport'])
        ->middleware('abilities:write');

    Route::get('club/sports', [ClubSportController::class, 'index'])->middleware('ability:read,write');
    Route::post('club/sports', [ClubSportController::class, 'store'])->middleware('abilities:write');
    Route::delete('club/sports/{sport}', [ClubSportController::class, 'destroy'])
        ->whereNumber('sport')
        ->middleware('abilities:write');

    Route::get('sports', [SportController::class, 'index'])->middleware('ability:read,write');
});
