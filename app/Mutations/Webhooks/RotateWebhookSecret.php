<?php

declare(strict_types=1);

namespace App\Mutations\Webhooks;

use App\Data\WebhookArgs;
use App\Data\WebhookSecretData;
use App\Models\WebhookEndpoint;
use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Attributes\ReactiveMutation;
use Kit\Reactive\Mutation;
use Spatie\LaravelData\Data;

/**
 * Replaces the signing secret and answers with the new one. Deliveries sent
 * from here on are signed with it, retries of older ones included, so the
 * receiver should switch before the next event.
 *
 * @extends Mutation<WebhookArgs>
 */
#[ReactiveMutation('webhooks.rotateSecret', result: WebhookSecretData::class)]
final class RotateWebhookSecret extends Mutation
{
    public static function args(): string
    {
        return WebhookArgs::class;
    }

    public function authorize(Authenticatable $user, Data $args): void
    {
        $this->gate($user)->authorize('update', WebhookEndpoint::query()->findOrFail($args->id));
    }

    public function handle(Data $args): WebhookSecretData
    {
        $endpoint = WebhookEndpoint::query()->findOrFail($args->id);
        $endpoint->secret = WebhookEndpoint::generateSecret();
        $endpoint->save();

        return WebhookSecretData::fromModel($endpoint);
    }
}
