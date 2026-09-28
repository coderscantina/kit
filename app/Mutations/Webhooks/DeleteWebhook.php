<?php

declare(strict_types=1);

namespace App\Mutations\Webhooks;

use App\Data\WebhookArgs;
use App\Models\WebhookEndpoint;
use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Attributes\ReactiveMutation;
use Kit\Reactive\Mutation;
use Spatie\LaravelData\Data;

/**
 * Removes an endpoint and its delivery log. A delivery already queued finds
 * no row and sends nothing.
 *
 * @extends Mutation<WebhookArgs>
 */
#[ReactiveMutation('webhooks.delete')]
final class DeleteWebhook extends Mutation
{
    public static function args(): string
    {
        return WebhookArgs::class;
    }

    public function authorize(Authenticatable $user, Data $args): void
    {
        $this->gate($user)->authorize('delete', WebhookEndpoint::query()->findOrFail($args->id));
    }

    public function handle(Data $args): bool
    {
        return (bool) WebhookEndpoint::query()->findOrFail($args->id)->delete();
    }
}
