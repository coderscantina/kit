<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Account\SessionRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the account's device list current, and enforces its one destructive
 * action. A session revoked from another browser is torn down here, on the
 * revoked browser's next request, which is the same contract AuthenticateSession
 * already gives for a password change.
 */
class TrackUserSession
{
    public function __construct(
        private readonly SessionRegistry $registry,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if (! $user instanceof User || ! $request->hasSession()) {
            return $next($request);
        }

        if ($this->registry->track($request, $user)) {
            $this->registry->forget($request);

            Auth::guard('web')->logout();
            Auth::forgetGuards();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'message' => __('auth.session_revoked'),
                'error_code' => 'SESSION_REVOKED',
            ], 401);
        }

        return $next($request);
    }
}
