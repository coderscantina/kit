<?php

declare(strict_types=1);

namespace App\Mutations\Webhooks;

use App\Data\WebhookArgs;
use App\Data\WebhookDeliveryData;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Attributes\ReactiveMutation;
use Kit\Reactive\Mutation;
use Spatie\LaravelData\Data;

/**
 * Queues a `webhook.test` event to one endpoint, whatever it listens for,
 * so a receiver can be checked before any record changes. It is delivered,
 * signed and retried like any other event.
 *
 * @extends Mutation<WebhookArgs>
 */
#[ReactiveMutation('webhooks.test', result: WebhookDeliveryData::class)]
final class SendTestWebhook extends Mutation
{
    public const string EVENT = 'webhook.test';

    public static function args(): string
    {
        return WebhookArgs::class;
    }

    public function authorize(Authenticatable $user, Data $args): void
    {
        $this->gate($user)->authorize('update', WebhookEndpoint::query()->findOrFail($args->id));
    }

    public function handle(Data $args): WebhookDeliveryData
    {
        $endpoint = WebhookEndpoint::query()->findOrFail($args->id);

        $delivery = $endpoint->deliveries()->create([
            'event' => self::EVENT,
            'payload' => [
                'type' => self::EVENT,
                'timestamp' => now()->toIso8601String(),
                'data' => ['endpointId' => $endpoint->id],
            ],
            'status' => WebhookDelivery::PENDING,
        ]);

        return WebhookDeliveryData::fromModel($delivery->refresh());
    }
}
