<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ImpersonationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\PasswordConfirmController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TwoFactorController;
use Illuminate\Support\Facades\Route;

// Session-cookie auth for the SPA. `/auth/csrf-cookie` is Sanctum's.
Route::prefix('auth')->name('auth.')->middleware('web')->group(function (): void {
    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('login', LoginController::class)->name('login');
        Route::post('register', RegisterController::class)->name('register');
        Route::post('password/forgot', [PasswordResetController::class, 'forgot'])->name('password.forgot');
        Route::post('password/reset', [PasswordResetController::class, 'reset'])->name('password.reset');
    });

    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:auth'])
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', MeController::class)->name('me');
        Route::post('logout', LogoutController::class)->name('logout');
        Route::post('email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:auth')
            ->name('verification.resend');
        Route::post('password/confirm', PasswordConfirmController::class)
            ->middleware('throttle:sensitive')
            ->name('password.confirm');

        // Everything that hands out or takes away a second factor has to prove
        // the session belongs to the account owner: a fresh password
        // confirmation, and never from inside an impersonation session.
        Route::prefix('2fa')->name('2fa.')->middleware(['throttle:sensitive', 'not-impersonating'])->group(function (): void {
            Route::post('setup', [TwoFactorController::class, 'setup'])->middleware('password.confirmed')->name('setup');
            Route::post('confirm', [TwoFactorController::class, 'confirm'])->middleware('password.confirmed')->name('confirm');
            Route::post('disable', [TwoFactorController::class, 'disable'])->middleware('password.confirmed')->name('disable');
            Route::post('backup-codes', [TwoFactorController::class, 'regenerateBackupCodes'])->middleware('password.confirmed')->name('backup-codes');
        });

        Route::post('impersonate', [ImpersonationController::class, 'store'])->middleware('throttle:sensitive')->name('impersonate');
        Route::delete('impersonate', [ImpersonationController::class, 'destroy'])->name('impersonate.stop');
    });
});
