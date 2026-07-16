<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustodyController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\QrController;
use App\Http\Controllers\Api\V1\RapidTestController;
use App\Http\Controllers\Api\V1\SamplingEventController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 routes  (prefix: /api/v1, middleware group: api)
|--------------------------------------------------------------------------
| Phase 2 — field side: auth, rapid tests, sampling events, QR, custody.
*/

// --- Auth -----------------------------------------------------------------
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// --- Authenticated + active-account routes --------------------------------
Route::middleware(['auth:sanctum', 'active'])->group(function () {

    // Single controlled file-download route (all stored files served through here).
    Route::get('files/{path}', [FileController::class, 'show'])
        ->where('path', '.*')
        ->name('files.show');

    // QR SVG for a sample part (label printing / reprints).
    Route::get('sample-parts/{samplePart}/qr.svg', [QrController::class, 'show'])
        ->name('sample-parts.qr');

    // Custody scanning — FSO and TRANSPORT in this phase.
    Route::middleware('role:FSO,TRANSPORT')->group(function () {
        Route::post('custody/scan', [CustodyController::class, 'scan']);
    });
    // Part lookup + timeline (any authenticated staff member).
    Route::get('custody/parts/{qrToken}', [CustodyController::class, 'showPart']);

    // Rapid tests — FSO only.
    Route::middleware('role:FSO')->group(function () {
        Route::get('rapid-tests', [RapidTestController::class, 'index']);
        Route::post('rapid-tests', [RapidTestController::class, 'store']);
    });

    // Sampling events — FSO only (ownership further enforced by policy).
    Route::middleware('role:FSO')->group(function () {
        Route::get('sampling-events', [SamplingEventController::class, 'index']);
        Route::post('sampling-events', [SamplingEventController::class, 'store']);
        Route::get('sampling-events/{samplingEvent}', [SamplingEventController::class, 'show']);
        Route::patch('sampling-events/{samplingEvent}', [SamplingEventController::class, 'update']);
        Route::post('sampling-events/{samplingEvent}/parts', [SamplingEventController::class, 'addPart']);
        Route::post('sampling-events/{samplingEvent}/finalize', [SamplingEventController::class, 'finalize']);
    });
});
