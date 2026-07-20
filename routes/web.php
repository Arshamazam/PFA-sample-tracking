<?php

use App\Http\Controllers\Web\Admin\EventController as AdminEventController;
use App\Http\Controllers\Web\Admin\SettingController;
use App\Http\Controllers\Web\Admin\SopViolationController as AdminSopViolationController;
use App\Http\Controllers\Web\Admin\TestCatalogController as AdminTestCatalogController;
use App\Http\Controllers\Web\Admin\UserController as AdminUserController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\PasswordController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DisputeController;
use App\Http\Controllers\Web\Fso\EventController as FsoEventController;
use App\Http\Controllers\Web\Fso\RapidTestController as FsoRapidTestController;
use App\Http\Controllers\Web\Fso\ScanController as FsoScanController;
use App\Http\Controllers\Web\LabController;
use App\Http\Controllers\Web\Registration\BlindCodingController;
use App\Http\Controllers\Web\Registration\DisputeFilingController;
use App\Http\Controllers\Web\Registration\ReceivingController;
use App\Http\Controllers\Web\Registration\RetentionController;
use App\Http\Controllers\Web\Registration\SectionController;
use App\Http\Controllers\Web\VerificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web (session) routes — the internal admin panel (Phase 5)
|--------------------------------------------------------------------------
| Same users table and role/active checks as the API; the Sanctum API is
| untouched. Web controllers reuse the SAME services (CustodyStateMachine,
| DisputeService, EventCodeGenerator, QrService) — no duplicated business logic.
*/

// --- Guest ---------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// --- Authenticated + active ---------------------------------------------
Route::middleware(['auth', 'active'])->group(function () {
    // Forced password change (reachable before the change is complete).
    Route::get('password/change', [PasswordController::class, 'edit'])->name('password.change');
    Route::post('password/change', [PasswordController::class, 'update'])->name('password.update');

    // Everything below also requires the interim password to have been rotated.
    Route::middleware('password.changed')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        /* ---------------- Registration Officer ---------------- */
        Route::middleware('role:REGISTRATION_OFFICER')->prefix('registration')->name('registration.')->group(function () {
            // Receiving desk
            Route::get('receiving', [ReceivingController::class, 'create'])->name('receiving.create');
            Route::get('receiving/{qr_token}', [ReceivingController::class, 'show'])->name('receiving.show');
            Route::post('receiving', [ReceivingController::class, 'store'])->name('receiving.store');
            // Blind coding
            Route::get('blind', [BlindCodingController::class, 'create'])->name('blind.create');
            Route::get('blind/{qr_token}', [BlindCodingController::class, 'show'])->name('blind.show');
            Route::post('blind', [BlindCodingController::class, 'store'])->name('blind.store');
            Route::get('blind/{sample_part}/label', [BlindCodingController::class, 'label'])->name('blind.label');
            // Section assignment
            Route::get('section', [SectionController::class, 'create'])->name('section.create');
            Route::get('section/{qr_token}', [SectionController::class, 'show'])->name('section.show');
            Route::post('section', [SectionController::class, 'store'])->name('section.store');
            // Dispute filing (officer files for a walk-in FBO)
            Route::get('dispute', [DisputeFilingController::class, 'create'])->name('disputes.create');
            Route::post('dispute', [DisputeFilingController::class, 'store'])->name('disputes.store');
        });

        // Retention shelf — registration officers and admins.
        Route::middleware('role:REGISTRATION_OFFICER,ADMIN')->prefix('registration')->name('registration.')->group(function () {
            Route::get('retention', [RetentionController::class, 'index'])->name('retention.index');
            Route::post('retention/destroy', [RetentionController::class, 'destroy'])->name('retention.destroy');
        });

        /* ---------------- Lab Analyst (blind) ---------------- */
        Route::middleware('role:LAB_ANALYST')->prefix('lab')->name('lab.')->group(function () {
            Route::get('/', [LabController::class, 'queue'])->name('queue');
            Route::get('{blind_code}', [LabController::class, 'show'])->name('show');
            Route::post('{blind_code}/start', [LabController::class, 'start'])->name('start');
            Route::post('{blind_code}/results', [LabController::class, 'results'])->name('results');
        });

        /* ---------------- Verifying Officer ---------------- */
        Route::middleware('role:VERIFYING_OFFICER')->prefix('verification')->name('verification.')->group(function () {
            Route::get('/', [VerificationController::class, 'queue'])->name('queue');
            Route::get('{blind_code}', [VerificationController::class, 'show'])->name('show');
            Route::post('{blind_code}/verdict', [VerificationController::class, 'verdict'])->name('verdict');
            Route::post('{blind_code}/return', [VerificationController::class, 'returnToAnalyst'])->name('return');
        });

        // Disputes — verifying officers and admins.
        Route::middleware('role:VERIFYING_OFFICER,ADMIN')->prefix('disputes')->name('disputes.')->group(function () {
            Route::get('/', [DisputeController::class, 'index'])->name('index');
            Route::get('{dispute}', [DisputeController::class, 'show'])->name('show');
            Route::post('{dispute}/decide', [DisputeController::class, 'decide'])->name('decide');
        });

        /* ---------------- Admin ---------------- */
        Route::middleware('role:ADMIN')->prefix('admin')->name('admin.')->group(function () {
            Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
            Route::get('users/create', [AdminUserController::class, 'create'])->name('users.create');
            Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
            Route::get('users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
            Route::put('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
            Route::post('users/{user}/reset-password-flag', [AdminUserController::class, 'resetPasswordFlag'])->name('users.reset-flag');

            Route::get('test-catalog', [AdminTestCatalogController::class, 'index'])->name('catalog.index');
            Route::get('test-catalog/create', [AdminTestCatalogController::class, 'create'])->name('catalog.create');
            Route::post('test-catalog', [AdminTestCatalogController::class, 'store'])->name('catalog.store');
            Route::get('test-catalog/{test_catalog}/edit', [AdminTestCatalogController::class, 'edit'])->name('catalog.edit');
            Route::put('test-catalog/{test_catalog}', [AdminTestCatalogController::class, 'update'])->name('catalog.update');
            Route::delete('test-catalog/{test_catalog}', [AdminTestCatalogController::class, 'destroy'])->name('catalog.destroy');

            Route::get('sop-violations', [AdminSopViolationController::class, 'index'])->name('violations.index');
            Route::post('sop-violations/{sop_violation}/resolve', [AdminSopViolationController::class, 'resolve'])->name('violations.resolve');

            Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

            Route::get('events', [AdminEventController::class, 'index'])->name('events.index');
            Route::get('events/{sampling_event}', [AdminEventController::class, 'show'])->name('events.show');
        });

        /* ---------------- FSO / TRANSPORT web fallback ---------------- */
        Route::middleware('role:FSO,TRANSPORT')->prefix('field')->name('fso.')->group(function () {
            Route::get('events', [FsoEventController::class, 'index'])->name('events.index');
            Route::get('events/create', [FsoEventController::class, 'create'])->name('events.create');
            Route::post('events', [FsoEventController::class, 'store'])->name('events.store');
            Route::get('events/{sampling_event}', [FsoEventController::class, 'show'])->name('events.show');
            Route::put('events/{sampling_event}', [FsoEventController::class, 'update'])->name('events.update');
            Route::post('events/{sampling_event}/parts', [FsoEventController::class, 'storePart'])->name('events.parts.store');
            Route::post('events/{sampling_event}/finalize', [FsoEventController::class, 'finalize'])->name('events.finalize');
            Route::get('events/{sampling_event}/labels', [FsoEventController::class, 'labels'])->name('events.labels');

            Route::get('rapid-test', [FsoRapidTestController::class, 'create'])->name('rapid.create');
            Route::post('rapid-test', [FsoRapidTestController::class, 'store'])->name('rapid.store');

            Route::get('scan', [FsoScanController::class, 'create'])->name('scan.create');
            Route::post('scan', [FsoScanController::class, 'store'])->name('scan.store');
        });
    });
});
