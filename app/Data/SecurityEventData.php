<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\SecurityEvent;
use App\Support\UserAgent;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SecurityEventData extends Data
{
    /**
     * @param  array<string, mixed>|null  $context
     */
    public function __construct(
        public string $id,
        /** One of the App\Models\SecurityEvent constants; the client translates it. */
        public string $event,
        public ?string $ipAddress,
        public string $device,
        public ?array $context,
        public string $createdAt,
    ) {}

    public static function fromModel(SecurityEvent $event): self
    {
        return new self(
            id: $event->id,
            event: $event->event,
            ipAddress: $event->ip_address,
            device: UserAgent::describe($event->user_agent),
            context: $event->context,
            createdAt: $event->created_at->toIso8601String(),
        );
    }
}
