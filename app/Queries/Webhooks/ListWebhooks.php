<?php

declare(strict_types=1);

namespace App\Queries\Webhooks;

use App\Data\WebhookEndpointData;
use App\Models\WebhookEndpoint;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Kit\Reactive\Attributes\ReactiveQuery;
use Kit\Reactive\NoArgs;
use Kit\Reactive\Query;
use Spatie\LaravelData\Data;

/**
 * Every webhook endpoint with the status of its latest delivery, so a
 * failing receiver shows on the list the moment it fails.
 *
 * @extends Query<NoArgs>
 */
#[ReactiveQuery('webhooks.list', result: WebhookEndpointData::class, list: true)]
final class ListWebhooks extends Query
{
    public static function args(): string
    {
        return NoArgs::class;
    }

    public function authorize(Authenticatable $user, Data $args): void
    {
        $this->gate($user)->authorize('viewAny', WebhookEndpoint::class);
    }

    /**
     * @return Collection<int, WebhookEndpointData>
     */
    public function handle(Data $args): mixed
    {
        return WebhookEndpointData::collect(
            WebhookEndpoint::query()->with('latestDelivery')->orderBy('id')->get(),
        );
    }
}
