<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Writes the account's security trail. One call site per interesting event,
 * so the page that shows the trail never has to guess what happened.
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
        return SecurityEvent::query()->create([
            'user_id' => $user->id,
            'event' => $event,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->userAgent(),
            'context' => $context === [] ? null : $context,
            'created_at' => now(),
        ]);
    }

    /** Long enough to identify a browser, short enough not to be a blob column in practice. */
    private function userAgent(): ?string
    {
        $agent = $this->request->userAgent();

        return is_string($agent) && $agent !== '' ? mb_substr($agent, 0, 512) : null;
    }
}
