<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;

/**
 * Support impersonation: a root user takes over another session and can
 * hand it back. The real user's id lives in the session so nothing about the
 * impersonated account changes.
 */
class ImpersonationService
{
    private const string KEY = 'auth.impersonator_id';

    public function start(Session $session, User $impersonator, User $target): void
    {
        $session->put(self::KEY, $impersonator->id);
        Auth::guard('web')->login($target);
        $session->regenerate();
    }

    public function stop(Session $session): ?User
    {
        $impersonatorId = $session->pull(self::KEY);

        if (! is_string($impersonatorId)) {
            return null;
        }

        $impersonator = User::query()->find($impersonatorId);

        if ($impersonator === null) {
            Auth::guard('web')->logout();

            return null;
        }

        Auth::guard('web')->login($impersonator);
        $session->regenerate();

        return $impersonator;
    }

    public function isImpersonating(Session $session): bool
    {
        return $session->has(self::KEY);
    }

    public function impersonatorId(Session $session): ?string
    {
        $id = $session->get(self::KEY);

        return is_string($id) ? $id : null;
    }
}
