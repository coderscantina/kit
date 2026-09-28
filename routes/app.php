<?php

declare(strict_types=1);

use App\Http\Controllers\Account\AccessTokenController;
use App\Http\Controllers\Account\AvatarController;
use App\Http\Controllers\Account\DataExportController;
use App\Http\Controllers\Account\EmailChangeController;
use App\Http\Controllers\Account\InviteController;
use App\Http\Controllers\Account\NotificationSettingsController;
use App\Http\Controllers\Account\PeopleController;
use App\Http\Controllers\Account\PhoneController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\PushSubscriptionController;
use App\Http\Controllers\Account\SavedViewController;
use App\Http\Controllers\Account\SecurityActivityController;
use App\Http\Controllers\Account\SessionController;
use App\Http\Controllers\Account\SocialLinkController;
use App\Http\Controllers\Account\UserController;
use App\Http\Controllers\Ai\AiStreamController;
use App\Http\Controllers\Records\AttachmentController;
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

            Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push.store');
            Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push.destroy');
            Route::post('push-subscriptions/test', [PushSubscriptionController::class, 'test'])
                ->middleware('throttle:sensitive')
                ->name('push.test');

            // The preferences matrix. The inbox itself is reactive
            // (notifications.list / notifications.summary), not REST.
            Route::get('notifications', [NotificationSettingsController::class, 'index'])->name('notifications.index');
            Route::put('notifications', [NotificationSettingsController::class, 'update'])->name('notifications.update');

            // Sending a code costs a message; guessing one must not be cheap.
            Route::post('phone', [PhoneController::class, 'store'])
                ->middleware('throttle:sensitive')
                ->name('phone.store');
            Route::post('phone/verify', [PhoneController::class, 'verify'])
                ->middleware('throttle:sensitive')
                ->name('phone.verify');
            Route::delete('phone', [PhoneController::class, 'destroy'])->name('phone.destroy');

            Route::get('social-links', [SocialLinkController::class, 'index'])->name('social-links.index');
            Route::delete('social-links/{provider}', [SocialLinkController::class, 'destroy'])
                ->middleware('password.confirmed')
                ->name('social-links.destroy');

            Route::get('security-activity', [SecurityActivityController::class, 'index'])->name('security-activity');

            // Private by definition: every query is scoped to the owner.
            Route::get('views', [SavedViewController::class, 'index'])->name('views.index');
            Route::post('views', [SavedViewController::class, 'store'])->name('views.store');
            Route::patch('views/{savedView}', [SavedViewController::class, 'update'])->name('views.update');
            Route::delete('views/{savedView}', [SavedViewController::class, 'destroy'])->name('views.destroy');

            // A token is a standing credential for the MCP endpoint, so
            // minting one asks for the password, like any credential change.
            Route::get('tokens', [AccessTokenController::class, 'index'])->name('tokens.index');
            Route::post('tokens', [AccessTokenController::class, 'store'])
                ->middleware(['password.confirmed', 'not-impersonating', 'throttle:sensitive'])
                ->name('tokens.store');
            Route::delete('tokens/{token}', [AccessTokenController::class, 'destroy'])->name('tokens.destroy');

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
        // Building a file costs more than a page of rows, so it counts against
        // the tighter limiter.
        Route::get('people/export', [PeopleController::class, 'export'])
            ->middleware('throttle:sensitive')
            ->name('people.export');

        // Its own limiter: an AI request costs money and seconds, so it is
        // counted apart from the 300/minute the rest of the API allows.
        Route::post('ai/stream', AiStreamController::class)->middleware('throttle:ai')->name('ai.stream');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}/avatar', [AvatarController::class, 'show'])->name('users.avatar');
        Route::get('roles', [UserController::class, 'roles'])->name('roles.index');
        Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->middleware('totp')->name('users.role');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('totp')->name('users.destroy');
        // Files on records. Who may do what is the record's own policy.
        Route::post('attachments', [AttachmentController::class, 'store'])->name('attachments.store');
        Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
        Route::get('attachments/{attachment}/thumbnail', [AttachmentController::class, 'thumbnail'])->name('attachments.thumbnail');
        Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

        // make:endpoint adds routes here, inside the authenticated group.
        // kit:api
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
