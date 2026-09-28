<?php

declare(strict_types=1);

namespace App\Mutations\Webhooks;

use App\Data\SaveWebhookArgs;
use App\Data\WebhookEndpointData;
use App\Models\WebhookEndpoint;
use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Attributes\ReactiveMutation;
use Kit\Reactive\Mutation;
use Spatie\LaravelData\Data;

/**
 * Changes where an endpoint points, what it listens for, or pauses it. A
 * paused endpoint fails its pending deliveries instead of sending them.
 *
 * @extends Mutation<SaveWebhookArgs>
 */
#[ReactiveMutation('webhooks.update', result: WebhookEndpointData::class)]
final class UpdateWebhook extends Mutation
{
    public static function args(): string
    {
        return SaveWebhookArgs::class;
    }

    public function authorize(Authenticatable $user, Data $args): void
    {
        $this->gate($user)->authorize('update', WebhookEndpoint::query()->findOrFail((string) $args->id));
    }

    public function handle(Data $args): WebhookEndpointData
    {
        $endpoint = WebhookEndpoint::query()->findOrFail((string) $args->id);

        $endpoint->fill([
            'url' => $args->url,
            'description' => $args->description,
            'events' => array_values($args->events),
            'active' => $args->active,
        ])->save();

        return WebhookEndpointData::fromModel($endpoint->load('latestDelivery'));
    }
}
