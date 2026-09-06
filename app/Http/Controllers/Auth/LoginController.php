<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Concerns\ThrottlesStepUpVerification;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Account\SecurityLog;
use App\Services\Auth\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    use ThrottlesStepUpVerification;

    public function __construct(
        private readonly TwoFactorAuthService $twoFactor,
        private readonly SecurityLog $securityLog,
    ) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $guard = Auth::guard('web');

        if (! $guard->attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            return response()->json(['message' => __('auth.failed')], 401);
        }

        /** @var User $user */
        $user = $guard->user();

        if ($user->hasEnabledTwoFactor()) {
            $code = $request->header('x-totp-code');

            if (! is_string($code) || $code === '') {
                $this->dropSession($request);

                return response()->json([
                    'message' => __('auth.totp_required'),
                    'error_code' => 'TOTP_VERIFICATION_REQUIRED',
                ], 423);
            }

            if ($lockout = $this->stepUpLockout($user, 'totp')) {
                $this->dropSession($request);

                return $lockout;
            }

            if (! $this->twoFactor->verifyForUser($user, $code)) {
                $this->recordStepUpFailure($user, 'totp');
                $this->dropSession($request);

                return response()->json([
                    'message' => __('auth.invalid_totp_code'),
                    'error_code' => 'INVALID_TOTP_CODE',
                ], 403);
            }

            $this->clearStepUpAttempts($user, 'totp');
        }

        if (! $user->hasVerifiedEmail()) {
            $this->dropSession($request);

            return response()->json([
                'message' => __('auth.email_not_verified'),
                'error_code' => 'EMAIL_NOT_VERIFIED',
            ], 409);
        }

        $user->last_login_at = now();
        $user->save();

        $this->securityLog->record($user, SecurityEvent::SIGNED_IN);

        $request->session()->regenerate();

        return response()->json(['message' => __('auth.login_successful')]);
    }

    private function dropSession(LoginRequest $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
