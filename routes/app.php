<?php

declare(strict_types=1);

use App\Http\Controllers\Account\AvatarController;
use App\Http\Controllers\Account\DataExportController;
use App\Http\Controllers\Account\EmailChangeController;
use App\Http\Controllers\Account\InviteController;
use App\Http\Controllers\Account\PeopleController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\SecurityActivityController;
use App\Http\Controllers\Account\SessionController;
use App\Http\Controllers\Account\UserController;
use App\Http\Controllers\Ai\AiStreamController;
use Illuminate\Support\Facades\Route;

// The REST remainder. Every authenticated group carries `app.access`, so a
// forgotten authorize() in a controller is a 403 rather than a leak.
Route::prefix('api')->name('api.')->middleware('web')->group(function (): void {
    Route::middleware(['auth:sanctum', 'app.access', 'throttle:app'])->group(function (): void {
        Route::prefix('account')->name('account.')->group(function (): void {
            Route::patch('profile', [ProfileController::class, 'update'])->name('profile');
            Route::put('password', [ProfileController::class, 'updatePassword'])->middleware('password.confirmed')->name('password');
            Route::delete('/', [ProfileController::class, 'destroy'])->middleware(['password.confirmed', 'not-impersonating'])->name('destroy');

            // Requesting the change is sensitive; cancelling one is not.
            Route::post('email', [EmailChangeController::class, 'store'])
                ->middleware(['password.confirmed', 'not-impersonating', 'throttle:sensitive'])
                ->name('email.request');
            Route::delete('email', [EmailChangeController::class, 'destroy'])->name('email.cancel');

            Route::post('avatar', [AvatarController::class, 'store'])->name('avatar.store');
            Route::delete('avatar', [AvatarController::class, 'destroy'])->name('avatar.destroy');

            Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
            Route::delete('sessions', [SessionController::class, 'destroyOthers'])
                ->middleware('password.confirmed')
                ->name('sessions.destroy-others');
            Route::delete('sessions/{session}', [SessionController::class, 'destroy'])
                ->middleware('password.confirmed')
                ->name('sessions.destroy');

            Route::get('security-activity', [SecurityActivityController::class, 'index'])->name('security-activity');

            Route::get('export', DataExportController::class)
                ->middleware(['password.confirmed', 'not-impersonating'])
                ->name('export');
        });

        Route::get('invites', [InviteController::class, 'index'])->name('invites.index');
        Route::post('invites', [InviteController::class, 'store'])->name('invites.store');
        Route::post('invites/{invite}/resend', [InviteController::class, 'resend'])->name('invites.resend');
        Route::delete('invites/{invite}', [InviteController::class, 'destroy'])->name('invites.destroy');

        // Accounts and outstanding invitations as one list.
        Route::get('people', [PeopleController::class, 'index'])->name('people.index');

        // Its own limiter: an AI request costs money and seconds, so it is
        // counted apart from the 300/minute the rest of the API allows.
        Route::post('ai/stream', AiStreamController::class)->middleware('throttle:ai')->name('ai.stream');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}/avatar', [AvatarController::class, 'show'])->name('users.avatar');
        Route::get('roles', [UserController::class, 'roles'])->name('roles.index');
        Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->middleware('totp')->name('users.role');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('totp')->name('users.destroy');
    });

    // Token-gated, session-free: the confirmation link is opened wherever the
    // mailbox is, which is often not the browser that asked for the change.
    Route::post('account/email/confirm/{change}', [EmailChangeController::class, 'confirm'])
        ->middleware('throttle:public')
        ->name('account.email.confirm');

    // Token-gated invite pages: reachable without a session because the
    // invitee usually has none yet.
    Route::prefix('invites/{invite}')->name('invites.public.')->middleware('throttle:public')->group(function (): void {
        Route::get('/', [InviteController::class, 'show'])->name('show');
        Route::post('accept', [InviteController::class, 'accept'])->name('accept');
        Route::post('decline', [InviteController::class, 'decline'])->name('decline');
    });
});
