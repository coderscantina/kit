<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Account\SecurityLog;
use Illuminate\Http\Request;

/**
 * What every sign-in does once the guard holds the user: stamp
 * `last_login_at`, write the security trail and rotate the session id.
 * The caller logs the user in first; the checks before that differ per path.
 */
class CompleteSignIn
{
    public function __construct(
        private readonly SecurityLog $securityLog,
    ) {}

    public function execute(Request $request, User $user): void
    {
        $user->last_login_at = now();
        $user->save();

        $this->securityLog->record($user, SecurityEvent::SIGNED_IN);

        $request->session()->regenerate();
    }
}
