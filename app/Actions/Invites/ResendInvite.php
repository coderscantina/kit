<?php

declare(strict_types=1);

namespace App\Actions\Invites;

use App\Models\Invite;
use App\Notifications\InviteNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Mails the invitation again. A fresh token, because the old one was mailed
 * and only its hash was kept, and a fresh expiry, because an invitation nobody
 * acted on is usually one that was never seen.
 */
class ResendInvite
{
    public function execute(Invite $invite): Invite
    {
        $token = Str::random(48);

        $invite->token_hash = Invite::hashToken($token);
        $invite->expires_at = now()->addDays((int) config('kit.invite_expiry_days'));
        $invite->save();

        Notification::route('mail', $invite->email)->notify(new InviteNotification($invite, $token));

        return $invite;
    }
}
