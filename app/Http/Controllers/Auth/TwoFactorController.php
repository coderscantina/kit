<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorCodeRequest;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Account\SecurityLog;
use App\Services\Auth\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TwoFactorController extends Controller
{
    private const int SETUP_TTL_MINUTES = 10;

    public function __construct(
        private readonly TwoFactorAuthService $twoFactor,
        private readonly SecurityLog $securityLog,
    ) {}

    /**
     * Generates a secret and parks it in the cache until confirm() proves
     * the authenticator has it. Nothing touches the user row until then.
     */
    public function setup(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasEnabledTwoFactor()) {
            return response()->json(['message' => __('auth.totp_already_enabled')], 409);
        }

        $secret = $this->twoFactor->generateSecret();
        Cache::put($this->setupKey($user), $secret, now()->addMinutes(self::SETUP_TTL_MINUTES));

        return response()->json([
            'secret' => $secret,
            'url' => $this->twoFactor->otpauthUrl((string) config('app.name'), $user->email, $secret),
        ]);
    }

    public function confirm(TwoFactorCodeRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasEnabledTwoFactor()) {
            return response()->json(['message' => __('auth.totp_already_enabled')], 409);
        }

        $secret = Cache::get($this->setupKey($user));

        if (! is_string($secret)) {
            return response()->json(['message' => __('auth.totp_setup_expired')], 410);
        }

        if (! $this->twoFactor->verify($secret, $request->string('code')->toString())) {
            return response()->json([
                'message' => __('auth.invalid_totp_code'),
                'error_code' => 'INVALID_TOTP_CODE',
            ], 422);
        }

        $codes = $this->twoFactor->generateBackupCodes();

        $user->two_factor_secret = $secret;
        $user->two_factor_backup_codes = $codes;
        $user->two_factor_confirmed_at = now();
        $user->save();

        Cache::forget($this->setupKey($user));
        $this->twoFactor->setGracePeriod($user->id);
        $this->securityLog->record($user, SecurityEvent::TWO_FACTOR_ENABLED);

        return response()->json(['backup_codes' => $codes]);
    }

    public function disable(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->two_factor_secret = null;
        $user->two_factor_backup_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        $this->twoFactor->clearGracePeriod($user->id);
        $this->securityLog->record($user, SecurityEvent::TWO_FACTOR_DISABLED);

        return response()->json(['message' => __('auth.totp_disabled')]);
    }

    public function regenerateBackupCodes(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasEnabledTwoFactor()) {
            return response()->json(['message' => __('auth.totp_not_enabled')], 409);
        }

        $codes = $this->twoFactor->generateBackupCodes();
        $user->two_factor_backup_codes = $codes;
        $user->save();

        $this->securityLog->record($user, SecurityEvent::BACKUP_CODES_REGENERATED);

        return response()->json(['backup_codes' => $codes]);
    }

    private function setupKey(User $user): string
    {
        return "totp-setup:{$user->id}";
    }
}
