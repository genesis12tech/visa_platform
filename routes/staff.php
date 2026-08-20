<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
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
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->defaults('guard', 'staff')
    ->middleware('auth:staff')
    ->name('staff.logout');
