<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Account\SecurityLog;
use App\Services\Account\SessionRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Ends the session. The guard fires Illuminate\Auth\Events\Logout, which the
 * reactive package listens to in order to purge the user's subscriptions.
 */
class LogoutController extends Controller
{
    public function __invoke(Request $request, SessionRegistry $registry, SecurityLog $securityLog): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $securityLog->record($user, SecurityEvent::SIGNED_OUT);
        }

        // Before the session is invalidated: afterwards the id has moved on
        // and the row would be orphaned until it expired.
        $registry->forget($request);

        Auth::guard('web')->logout();
        // The sanctum request guard caches the resolved user for the life of
        // the container; drop every guard so nothing later in this process
        // still sees the signed-out user (Octane does the same per request).
        Auth::forgetGuards();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
