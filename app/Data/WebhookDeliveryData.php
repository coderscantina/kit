<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\WebhookDelivery;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class WebhookDeliveryData extends Data
{
    public function __construct(
        public string $id,
        public string $event,
        /** `pending`, `succeeded` or `failed`. */
        public string $status,
        public int $attempts,
        public ?int $responseStatus,
        public ?string $responseBody,
        /** The body exactly as it is signed and sent. */
        public string $payload,
        public ?string $deliveredAt,
        public string $createdAt,
    ) {}

    public static function fromModel(WebhookDelivery $delivery): self
    {
        return new self(
            id: $delivery->id,
            event: $delivery->event,
            status: $delivery->status,
            attempts: $delivery->attempts,
            responseStatus: $delivery->response_status,
            responseBody: $delivery->response_body,
            payload: (string) json_encode($delivery->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            deliveredAt: $delivery->delivered_at?->toIso8601String(),
            createdAt: $delivery->created_at?->toIso8601String() ?? '',
        );
    }
}
