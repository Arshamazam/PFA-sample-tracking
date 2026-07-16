<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 routes  (prefix: /api/v1, middleware group: api)
|--------------------------------------------------------------------------
| Phase 2 — field side: auth, rapid tests, sampling events, QR, custody.
*/

// --- Auth -----------------------------------------------------------------
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// --- Authenticated + active-account routes --------------------------------
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    // Endpoints added incrementally below as controllers are built.
});
