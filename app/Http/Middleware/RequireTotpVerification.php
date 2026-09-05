<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\ThrottlesStepUpVerification;
use App\Services\Auth\PasswordConfirmation;
use App\Services\Auth\TwoFactorAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Step-up for sensitive routes. A user with 2FA enabled sends the current
 * code in `X-TOTP-Code` once and gets a grace window; a user without 2FA
 * falls back to the password confirmation window.
 */
class RequireTotpVerification
{
    use ThrottlesStepUpVerification;

    public function __construct(
        private readonly TwoFactorAuthService $twoFactor,
        private readonly PasswordConfirmation $confirmation,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => __('auth.unauthenticated')], 401);
        }

        if (! $user->hasEnabledTwoFactor()) {
            if (! $this->confirmation->isFresh($request->session())) {
                return response()->json([
                    'message' => __('auth.password_confirmation_required'),
                    'error_code' => 'PASSWORD_CONFIRMATION_REQUIRED',
                ], 423);
            }

            return $next($request);
        }

        if ($this->twoFactor->hasGracePeriod($user->id)) {
            return $next($request);
        }

        $code = $request->header('x-totp-code');

        if (! is_string($code) || $code === '') {
            return response()->json([
                'message' => __('auth.totp_required'),
                'error_code' => 'TOTP_VERIFICATION_REQUIRED',
            ], 423);
        }

        if ($lockout = $this->stepUpLockout($user, 'totp')) {
            return $lockout;
        }

        if (! $this->twoFactor->verifyForUser($user, $code)) {
            $this->recordStepUpFailure($user, 'totp');

            return response()->json([
                'message' => __('auth.invalid_totp_code'),
                'error_code' => 'INVALID_TOTP_CODE',
            ], 403);
        }

        $this->clearStepUpAttempts($user, 'totp');
        $this->twoFactor->setGracePeriod($user->id);

        return $next($request);
    }
}
