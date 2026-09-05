<?php

declare(strict_types=1);

namespace App\Http\Middleware\Concerns;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Attempt counter for step-up verification.
 *
 * Password confirmations and TOTP codes are checked on ordinary app requests,
 * which are throttled per minute in the hundreds. That is ample room to walk
 * a six-digit TOTP (three codes are valid at any moment given the ±1 window)
 * or brute-force the account password from a hijacked session, so these
 * checks need a counter of their own.
 */
trait ThrottlesStepUpVerification
{
    private const int MAX_ATTEMPTS = 5;

    private const int DECAY_SECONDS = 900;

    private function stepUpKey(User $user, string $factor): string
    {
        return "step-up:{$factor}:{$user->getKey()}";
    }

    private function stepUpLockout(User $user, string $factor): ?JsonResponse
    {
        $key = $this->stepUpKey($user, $factor);

        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return null;
        }

        $seconds = RateLimiter::availableIn($key);

        return response()->json([
            'message' => __('auth.too_many_verification_attempts', ['seconds' => $seconds]),
            'error_code' => 'TOO_MANY_ATTEMPTS',
            'retry_after' => $seconds,
        ], 429);
    }

    private function recordStepUpFailure(User $user, string $factor): void
    {
        RateLimiter::hit($this->stepUpKey($user, $factor), self::DECAY_SECONDS);
    }

    private function clearStepUpAttempts(User $user, string $factor): void
    {
        RateLimiter::clear($this->stepUpKey($user, $factor));
    }
}
