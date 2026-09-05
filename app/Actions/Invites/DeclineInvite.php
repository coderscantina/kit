<?php

declare(strict_types=1);

namespace App\Actions\Invites;

use App\Models\Invite;
use Illuminate\Auth\Access\AuthorizationException;

class DeclineInvite
{
    public function execute(Invite $invite, string $token): void
    {
        if (! $invite->isPending() || ! $invite->tokenMatches($token)) {
            throw new AuthorizationException(__('auth.invite_invalid'));
        }

        $invite->declined_at = now();
        $invite->save();
    }
}
