<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\EmailChange;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\EmailChangedNotification;
use App\Services\Account\SecurityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Lands a confirmed address change. Opening the link is proof of control of
 * the new inbox, so the address arrives verified.
 */
class ConfirmEmailChange
{
    public function __construct(
        private readonly SecurityLog $securityLog,
    ) {}

    public function execute(EmailChange $change, string $token): User
    {
        abort_unless($change->isPending() && $change->tokenMatches($token), 404);

        $previous = '';

        $user = DB::transaction(function () use ($change, &$previous): User {
            /** @var User $user */
            $user = User::query()->lockForUpdate()->findOrFail($change->user_id);

            // The address may have been taken since the request was made.
            if (User::query()->whereKeyNot($user->id)->where('email', $change->new_email)->exists()) {
                throw ValidationException::withMessages(['email' => __('auth.email_taken')]);
            }

            $previous = $user->email;
            $user->email = $change->new_email;
            $user->email_verified_at = now();
            $user->save();

            $change->delete();

            return $user;
        });

        // To the address that is losing the account, which is the one that
        // needs to hear about it.
        Notification::route('mail', $previous)->notify(new EmailChangedNotification($user->email));

        $this->securityLog->record($user, SecurityEvent::EMAIL_CHANGED, ['email' => $user->email]);

        return $user;
    }
}
