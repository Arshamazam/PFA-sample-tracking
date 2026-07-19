<?php

use App\Http\Controllers\Api\V1\Admin\AdminSopViolationController;
use App\Http\Controllers\Api\V1\Admin\AdminTestCatalogController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustodyController;
use App\Http\Controllers\Api\V1\DisputeController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\LabController;
use App\Http\Controllers\Api\V1\QrController;
use App\Http\Controllers\Api\V1\RapidTestController;
use App\Http\Controllers\Api\V1\RegistrationController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SamplingEventController;
use App\Http\Controllers\Api\V1\VerificationController;
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
    // Part lookup + timeline. This returns the FULL de-blinded record (event,
    // premises, custody trail), so LAB_ANALYST must be excluded — otherwise it is a
    // back door around the blind wall. Locked by BlindWallTest.
    Route::get('custody/parts/{qrToken}', [CustodyController::class, 'showPart'])
        ->middleware('role:FSO,TRANSPORT,REGISTRATION_OFFICER,VERIFYING_OFFICER,ADMIN');

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

    /*
    |----------------------------------------------------------------------
    | Phase 3 — Technical Wing
    |----------------------------------------------------------------------
    */

    // Registration Section — works from the physical QR on the sample.
    Route::middleware('role:REGISTRATION_OFFICER')->prefix('registration')->group(function () {
        Route::post('receive', [RegistrationController::class, 'receive']);
        Route::post('retain', [RegistrationController::class, 'retain']);
        Route::post('blind-code', [RegistrationController::class, 'blindCode']);
        Route::post('assign-section', [RegistrationController::class, 'assignSection']);
        Route::get('suggest-section', [RegistrationController::class, 'suggestSection']);
    });

    // Retention + destruction — registration officers and admins.
    Route::middleware('role:REGISTRATION_OFFICER,ADMIN')->prefix('registration')->group(function () {
        Route::get('retention', [RegistrationController::class, 'retention']);
        Route::post('destroy', [RegistrationController::class, 'destroy']);
    });

    // Lab workbench — BEHIND THE BLIND WALL. Addressed only by blind_code, and
    // every response here must be a BlindSamplePartResource.
    Route::middleware('role:LAB_ANALYST')->prefix('lab')->group(function () {
        Route::get('queue', [LabController::class, 'queue']);
        Route::post('{blindCode}/start', [LabController::class, 'start']);
        Route::post('{blindCode}/results', [LabController::class, 'storeResults']);
    });

    // Verification (maker-checker) — sees the full de-blinded record.
    Route::middleware('role:VERIFYING_OFFICER')->prefix('verification')->group(function () {
        Route::get('queue', [VerificationController::class, 'queue']);
        Route::post('{blindCode}/verdict', [VerificationController::class, 'verdict']);
        Route::post('{blindCode}/return', [VerificationController::class, 'returnToAnalyst']);
    });

    // Report download — role check lives in the controller because the owning FSO
    // is allowed too (and analysts are explicitly excluded).
    Route::get('reports/{blindCode}.pdf', [ReportController::class, 'show'])->name('reports.show');

    /*
    |----------------------------------------------------------------------
    | Phase 4 — Disputes, resampling, reference lifecycle
    |----------------------------------------------------------------------
    */

    // Filing is internal for now (officer files for a walk-in FBO); Phase 6's public
    // route will reuse DisputeService.
    Route::middleware('role:REGISTRATION_OFFICER,ADMIN')
        ->post('disputes', [DisputeController::class, 'store']);

    // Listing + deciding — verifying officers and admins (maker-checker in the service).
    Route::middleware('role:VERIFYING_OFFICER,ADMIN')->group(function () {
        Route::get('disputes', [DisputeController::class, 'index']);
        Route::post('disputes/{dispute}/decide', [DisputeController::class, 'decide']);
    });

    // Full de-blinded event detail — everyone who legitimately sees the business
    // (owning FSO enforced in the controller); analysts excluded.
    Route::middleware('role:FSO,REGISTRATION_OFFICER,VERIFYING_OFFICER,ADMIN')
        ->get('events/{samplingEvent}/detail', [EventController::class, 'detail']);

    // Admin essentials.
    Route::middleware('role:ADMIN')->prefix('admin')->group(function () {
        Route::apiResource('users', AdminUserController::class)->except(['destroy']);
        Route::apiResource('test-catalog', AdminTestCatalogController::class)
            ->parameters(['test-catalog' => 'testCatalog']);
        Route::get('sop-violations', [AdminSopViolationController::class, 'index']);
        Route::patch('sop-violations/{sopViolation}', [AdminSopViolationController::class, 'update']);
    });
});
