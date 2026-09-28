<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\WebhookEndpoint;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The answer to creating an endpoint or rotating its secret: the only two
 * moments the secret leaves the server. It is a mutation result, so only
 * the caller gets it; nothing pushes it to other screens.
 */
#[TypeScript]
final class WebhookSecretData extends Data
{
    public function __construct(
        public WebhookEndpointData $endpoint,
        public string $secret,
    ) {}

    public static function fromModel(WebhookEndpoint $endpoint): self
    {
        return new self(WebhookEndpointData::fromModel($endpoint->load('latestDelivery')), $endpoint->secret);
    }
}
