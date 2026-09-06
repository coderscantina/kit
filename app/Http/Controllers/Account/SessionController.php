<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Data\SessionData;
use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Account\SecurityLog;
use App\Services\Account\SessionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The browsers holding a session for this account. Revocation lands on the
 * revoked browser's next request; see App\Services\Account\SessionRegistry
 * for why that is the contract rather than an instant kill.
 */
class SessionController extends Controller
{
    public function __construct(
        private readonly SessionRegistry $registry,
        private readonly SecurityLog $securityLog,
    ) {}

    /**
     * @return array<int, SessionData>
     */
    public function index(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $currentKey = UserSession::key($request->session()->getId());

        return $this->registry->list($user)
            ->map(fn (UserSession $session): SessionData => SessionData::fromModel($session, $currentKey))
            ->all();
    }

    public function destroy(Request $request, string $session): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_if(hash_equals(UserSession::key($request->session()->getId()), $session), 422, __('auth.session_is_current'));
        abort_unless($this->registry->revoke($user, $session), 404);

        $this->securityLog->record($user, SecurityEvent::SESSION_REVOKED, ['count' => 1]);

        return response()->json(['revoked' => 1]);
    }

    public function destroyOthers(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $revoked = $this->registry->revokeOthers($user, $request->session()->getId());

        if ($revoked > 0) {
            $this->securityLog->record($user, SecurityEvent::SESSION_REVOKED, ['count' => $revoked]);
        }

        return response()->json(['revoked' => $revoked]);
    }
}
