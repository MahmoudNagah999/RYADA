<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\RegisterClubController;
use Illuminate\Support\Facades\Route;

// Convention: read routes use `ability:read,write` (any of); write routes use `abilities:write`.
// Convention: every club-scoped route goes inside the `tenant` group (after auth:sanctum).
// Convention: public endpoints that can be abused get their own limiter (`throttle:<name>`).

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('register-club', RegisterClubController::class)->middleware('throttle:register');

    Route::middleware('auth:sanctum')->group(function () {
        // Logout is allowed for any valid token, whatever its abilities or club.
        Route::post('logout', [AuthController::class, 'logout']);

        Route::middleware('tenant')->group(function () {
            Route::get('me', [AuthController::class, 'me'])->middleware('ability:read,write');
        });
    });
});
