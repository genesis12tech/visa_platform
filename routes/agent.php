<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Agent Portal Routes
|--------------------------------------------------------------------------
|
| Registered under Route::domain(config('app.agent_domain')) with the
| 'agent-portal' middleware group (bootstrap/app.php) — session guard
| 'agent', cookie name 'visa_agent_session'.
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest:agent')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->defaults('guard', 'agent')
        ->name('agent.login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->defaults('guard', 'agent');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('agent.password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->defaults('broker', 'agents');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('agent.password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->defaults('broker', 'agents')->name('agent.password.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->defaults('guard', 'agent')
    ->middleware('auth:agent')
    ->name('agent.logout');
