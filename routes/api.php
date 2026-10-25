<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\RegisterClubController;
use Illuminate\Support\Facades\Route;

// The final /api/v1 structure is consolidated in #10.
// Convention: read routes use `ability:read,write` (any of); write routes use `abilities:write`.
// Convention: every club-scoped route goes inside the `tenant` group (after auth:sanctum).
Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('register-club', RegisterClubController::class);

        Route::middleware('auth:sanctum')->group(function () {
            // Logout is allowed for any valid token, whatever its abilities or club.
            Route::post('logout', [AuthController::class, 'logout']);

            Route::middleware('tenant')->group(function () {
                Route::get('me', [AuthController::class, 'me'])->middleware('ability:read,write');
            });
        });
    });
});
