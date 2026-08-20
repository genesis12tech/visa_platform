<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Controllers\Auth\MfaEnrollmentController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Staff Portal Routes
|--------------------------------------------------------------------------
|
| Registered under Route::domain(config('app.staff_domain')) with the
| 'staff-portal' middleware group (bootstrap/app.php) — session guard
| 'staff', cookie name 'visa_staff_session'.
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest:staff')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->defaults('guard', 'staff')
        ->name('staff.login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->defaults('guard', 'staff');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('staff.password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->defaults('broker', 'staff');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('staff.password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->defaults('broker', 'staff')->name('staff.password.store');

    // Reachable mid-login, before Auth::guard('staff')->login() has run —
    // gated by the mfa_pending_user_id session key inside the controller,
    // not by the 'staff' guard's authenticated state.
    Route::get('/mfa/challenge', [MfaChallengeController::class, 'create'])->name('staff.mfa.challenge');
    Route::post('/mfa/challenge', [MfaChallengeController::class, 'store'])->name('staff.mfa.challenge.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->defaults('guard', 'staff')
    ->middleware('auth:staff')
    ->name('staff.logout');

// Backend_schema.md §11.4 — MFA is mandatory for staff. EnsureMfaEnrolled
// (applied to the whole 'staff-portal' group) exempts these routes by name.
Route::middleware('auth:staff')->group(function () {
    Route::get('/mfa/enroll', [MfaEnrollmentController::class, 'create'])->name('staff.mfa.enroll');
    Route::post('/mfa/enroll', [MfaEnrollmentController::class, 'store'])->name('staff.mfa.enroll.store');
    Route::get('/mfa/recovery-codes', [MfaEnrollmentController::class, 'showRecoveryCodes'])->name('staff.mfa.recovery-codes');
});
