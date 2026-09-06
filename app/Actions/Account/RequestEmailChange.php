<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\EmailChange;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\ConfirmEmailChangeNotification;
use App\Notifications\EmailChangeRequestedNotification;
use App\Services\Account\SecurityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Parks the new address and mails it a one-time link. Nothing on the account
 * moves yet: an address change is the lever every account takeover pulls, so
 * proving control of the new inbox comes first, and the old inbox is told
 * while it can still do something about it.
 *
 * A second request replaces the first, so asking again just refreshes the link.
 */
class RequestEmailChange
{
    public function __construct(
        private readonly SecurityLog $securityLog,
    ) {}

    public function execute(User $user, string $email): EmailChange
    {
        $token = Str::random(48);

        $change = DB::transaction(function () use ($user, $email, $token): EmailChange {
            EmailChange::query()->where('user_id', $user->id)->delete();

            return EmailChange::query()->create([
                'user_id' => $user->id,
                'new_email' => $email,
                'token_hash' => EmailChange::hashToken($token),
                'expires_at' => now()->addHours((int) config('kit.email_change_expiry_hours')),
            ]);
        });

        Notification::route('mail', $email)->notify(new ConfirmEmailChangeNotification($change, $token));
        $user->notify(new EmailChangeRequestedNotification($email));

        $this->securityLog->record($user, SecurityEvent::EMAIL_CHANGE_REQUESTED, ['email' => $email]);

        return $change;
    }
}
