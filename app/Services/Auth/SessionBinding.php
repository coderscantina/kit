<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;

/**
 * Sanctum's AuthenticateSession binds a session to the password hash of the
 * user it was created for and logs out on mismatch. Anything that swaps the
 * user inside a session (impersonation) or changes the hash (password
 * change) has to rebind, or the very next request is a 401.
 */
class SessionBinding
{
    public function rebind(Session $session, User $user): void
    {
        $guard = Auth::guard('web');

        $session->put('password_hash_web', $guard instanceof SessionGuard
            ? $guard->hashPasswordForCookie($user->getAuthPassword())
            : $user->getAuthPassword());
    }
}
