<?php

declare(strict_types=1);

namespace App\Actions\Invites;

use App\Models\Invite;
use App\Models\Role;
use App\Models\User;
use App\Notifications\InviteNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Issues an invite and mails the one-time token. A pending invite for the
 * same address is replaced, so re-inviting refreshes the expiry.
 */
class CreateInvite
{
    public function execute(string $email, Role $role, User $invitedBy): Invite
    {
        $token = Str::random(48);

        $invite = DB::transaction(function () use ($email, $role, $invitedBy, $token) {
            Invite::query()->where('email', $email)->pending()->delete();

            return Invite::query()->create([
                'email' => $email,
                'role_id' => $role->id,
                'invited_by' => $invitedBy->id,
                'token_hash' => Invite::hashToken($token),
                'expires_at' => now()->addDays((int) config('kit.invite_expiry_days')),
            ]);
        });

        Notification::route('mail', $email)->notify(new InviteNotification($invite, $token));

        return $invite;
    }
}
