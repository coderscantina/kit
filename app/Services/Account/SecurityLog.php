<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\SecurityAlertNotification;
use Illuminate\Http\Request;

/**
 * Writes the account's security trail. One call site per interesting event,
 * so the page that shows the trail never has to guess what happened.
 *
 * A credential change also raises a notification. Putting that here rather
 * than at each call site means a new way of changing a password cannot ship
 * without telling the owner about it.
 */
class SecurityLog
{
    public function __construct(
        private readonly Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(User $user, string $event, array $context = []): SecurityEvent
    {
        $agent = $this->userAgent();

        $record = SecurityEvent::query()->create([
            'user_id' => $user->id,
            'event' => $event,
            'ip_address' => $this->request->ip(),
            'user_agent' => $agent,
            'context' => $context === [] ? null : $context,
            'created_at' => now(),
        ]);

        if (in_array($event, SecurityAlertNotification::EVENTS, true)) {
            $user->notify(new SecurityAlertNotification($event, $this->request->ip(), $agent));
        }

        return $record;
    }

    /** Long enough to identify a browser, short enough not to be a blob column in practice. */
    private function userAgent(): ?string
    {
        $agent = $this->request->userAgent();

        return is_string($agent) && $agent !== '' ? mb_substr($agent, 0, 512) : null;
    }
}
