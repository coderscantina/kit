<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * The account's list of signed-in browsers, kept beside the session store
 * rather than inside it so it works whichever session driver is configured.
 *
 * Revocation is a flag, not a delete: the revoked browser trips it on its next
 * request and is signed out there. That is the same "effective on the next
 * request" contract the framework's own AuthenticateSession already gives for
 * a password change, and it buys a table that holds no replayable session id.
 */
class SessionRegistry
{
    /** How long a row may go untouched before a write refreshes it. */
    private const int TOUCH_SECONDS = 60;

    /**
     * Record this browser, refresh its last-seen stamp, and report whether it
     * has been revoked. One query per request: the same row answers both
     * questions, so checking for revocation costs nothing extra.
     *
     * @return bool True when the session was revoked and has to be torn down.
     */
    public function track(Request $request, User $user): bool
    {
        $key = UserSession::key($request->session()->getId());
        $existing = UserSession::query()->find($key);

        if ($existing === null) {
            UserSession::query()->create([
                'id' => $key,
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $this->userAgent($request),
                'last_active_at' => now(),
                'created_at' => now(),
            ]);

            return false;
        }

        if ($existing->revoked_at !== null) {
            return true;
        }

        // A write per request would turn every page view into a session write.
        if ($existing->last_active_at->diffInSeconds(now()) >= self::TOUCH_SECONDS) {
            $existing->last_active_at = now();
            $existing->save();
        }

        return false;
    }

    /**
     * The account's live sessions, freshest first. Expired rows are dropped on
     * the way, so the list maintains itself without a scheduled job.
     *
     * @return Collection<int, UserSession>
     */
    public function list(User $user): Collection
    {
        $this->prune();

        return UserSession::query()
            ->where('user_id', $user->id)
            ->live()
            ->orderByDesc('last_active_at')
            ->get();
    }

    /** @return int Number of sessions marked. */
    public function revokeOthers(User $user, string $currentSessionId): int
    {
        return UserSession::query()
            ->where('user_id', $user->id)
            ->whereKeyNot(UserSession::key($currentSessionId))
            ->live()
            ->update(['revoked_at' => now()]);
    }

    public function revoke(User $user, string $key): bool
    {
        return UserSession::query()
            ->where('user_id', $user->id)
            ->whereKey($key)
            ->live()
            ->update(['revoked_at' => now()]) > 0;
    }

    public function forget(Request $request): void
    {
        UserSession::query()->whereKey(UserSession::key($request->session()->getId()))->delete();
    }

    /** Rows the session store has already let expire, plus revoked leftovers. */
    private function prune(): void
    {
        UserSession::query()
            ->where('last_active_at', '<', now()->subMinutes((int) config('session.lifetime')))
            ->delete();
    }

    private function userAgent(Request $request): ?string
    {
        $agent = $request->userAgent();

        return is_string($agent) && $agent !== '' ? mb_substr($agent, 0, 512) : null;
    }
}
