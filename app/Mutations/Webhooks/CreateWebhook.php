<?php

declare(strict_types=1);

namespace App\Mutations\Webhooks;

use App\Data\SaveWebhookArgs;
use App\Data\WebhookSecretData;
use App\Models\WebhookEndpoint;
use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Attributes\ReactiveMutation;
use Kit\Reactive\Mutation;
use Spatie\LaravelData\Data;

/**
 * Adds an endpoint and answers with its signing secret, the one time the
 * secret is shown without asking for a new one.
 *
 * @extends Mutation<SaveWebhookArgs>
 */
#[ReactiveMutation('webhooks.create', result: WebhookSecretData::class)]
final class CreateWebhook extends Mutation
{
    public static function args(): string
    {
        return SaveWebhookArgs::class;
    }

    public function authorize(Authenticatable $user, Data $args): void
    {
        $this->gate($user)->authorize('create', WebhookEndpoint::class);
    }

    public function handle(Data $args): WebhookSecretData
    {
        $endpoint = WebhookEndpoint::query()->create([
            'url' => $args->url,
            'description' => $args->description,
            'events' => array_values($args->events),
            'active' => $args->active,
            'secret' => WebhookEndpoint::generateSecret(),
        ]);

        return WebhookSecretData::fromModel($endpoint);
    }
}
