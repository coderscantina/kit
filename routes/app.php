<?php

declare(strict_types=1);

use App\Http\Controllers\Account\InviteController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\UserController;
use Illuminate\Support\Facades\Route;

// The REST remainder. Every authenticated group carries `app.access`, so a
// forgotten authorize() in a controller is a 403 rather than a leak.
Route::prefix('api')->name('api.')->middleware('web')->group(function (): void {
    Route::middleware(['auth:sanctum', 'app.access', 'throttle:app'])->group(function (): void {
        Route::prefix('account')->name('account.')->group(function (): void {
            Route::patch('profile', [ProfileController::class, 'update'])->name('profile');
            Route::patch('email', [ProfileController::class, 'updateEmail'])->middleware('password.confirmed')->name('email');
            Route::put('password', [ProfileController::class, 'updatePassword'])->middleware('password.confirmed')->name('password');
            Route::delete('/', [ProfileController::class, 'destroy'])->middleware(['password.confirmed', 'not-impersonating'])->name('destroy');
        });

        Route::get('invites', [InviteController::class, 'index'])->name('invites.index');
        Route::post('invites', [InviteController::class, 'store'])->name('invites.store');
        Route::delete('invites/{invite}', [InviteController::class, 'destroy'])->name('invites.destroy');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('roles', [UserController::class, 'roles'])->name('roles.index');
        Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->middleware('totp')->name('users.role');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('totp')->name('users.destroy');
    });

    // Token-gated invite pages: reachable without a session because the
    // invitee usually has none yet.
    Route::prefix('invites/{invite}')->name('invites.public.')->middleware('throttle:public')->group(function (): void {
        Route::get('/', [InviteController::class, 'show'])->name('show');
        Route::post('accept', [InviteController::class, 'accept'])->name('accept');
        Route::post('decline', [InviteController::class, 'decline'])->name('decline');
    });
});
