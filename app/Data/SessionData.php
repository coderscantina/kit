<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\UserSession;
use App\Support\UserAgent;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SessionData extends Data
{
    public function __construct(
        public string $id,
        public string $device,
        public ?string $ipAddress,
        public string $lastActiveAt,
        public string $createdAt,
        /** The browser reading this response. It is the one row that cannot be revoked. */
        public bool $current,
    ) {}

    public static function fromModel(UserSession $session, string $currentKey): self
    {
        return new self(
            id: $session->id,
            device: UserAgent::describe($session->user_agent),
            ipAddress: $session->ip_address,
            lastActiveAt: $session->last_active_at->toIso8601String(),
            createdAt: $session->created_at->toIso8601String(),
            current: hash_equals($session->id, $currentKey),
        );
    }
}
