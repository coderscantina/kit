<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Contracts\Session\Session;

/**
 * Remembers in the session when the user last re-entered their password.
 */
class PasswordConfirmation
{
    private const string KEY = 'auth.password_confirmed_at';

    public function confirm(Session $session): void
    {
        $session->put(self::KEY, now()->getTimestamp());
    }

    public function isFresh(Session $session): bool
    {
        $confirmedAt = $session->get(self::KEY);

        if (! is_int($confirmedAt)) {
            return false;
        }

        return (now()->getTimestamp() - $confirmedAt) < (int) config('kit.password_confirmation_minutes') * 60;
    }

    public function forget(Session $session): void
    {
        $session->forget(self::KEY);
    }
}
