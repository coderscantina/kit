<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\WebhookEndpoint;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** An endpoint as the settings page lists it. The secret is never in here. */
#[TypeScript]
final class WebhookEndpointData extends Data
{
    /**
     * @param  array<int, string>  $events
     */
    public function __construct(
        public string $id,
        public string $url,
        public ?string $description,
        public array $events,
        public bool $active,
        /** `pending`, `succeeded` or `failed`; null before the first delivery. */
        public ?string $lastStatus,
        public ?string $lastEventAt,
        public string $createdAt,
    ) {}

    public static function fromModel(WebhookEndpoint $endpoint): self
    {
        $last = $endpoint->latestDelivery;

        return new self(
            id: $endpoint->id,
            url: $endpoint->url,
            description: $endpoint->description,
            events: $endpoint->events,
            active: $endpoint->active,
            lastStatus: $last?->status,
            lastEventAt: $last?->created_at?->toIso8601String(),
            createdAt: $endpoint->created_at?->toIso8601String() ?? '',
        );
    }
}
