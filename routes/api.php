<?php

use Illuminate\Support\Facades\Route;

// Each API version lives in its own file. The `api` prefix and the `api` middleware group
// come from bootstrap/app.php. A future v2 is added here as another group.
Route::prefix('v1')->middleware('throttle:api')->group(base_path('routes/api/v1.php'));
