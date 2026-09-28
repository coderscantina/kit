<?php

declare(strict_types=1);

namespace App\Queries\Webhooks;

use App\Data\WebhookArgs;
use App\Data\WebhookDeliveryData;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Kit\Reactive\Attributes\ReactiveQuery;
use Kit\Reactive\Dep;
use Kit\Reactive\Query;
use Spatie\LaravelData\Data;

/**
 * One endpoint's latest deliveries, live as they are attempted: what was
 * sent, what came back, how many tries it took.
 *
 * @extends Query<WebhookArgs>
 */
#[ReactiveQuery('webhooks.deliveries', result: WebhookDeliveryData::class, list: true)]
final class ListWebhookDeliveries extends Query
{
    public static function args(): string
    {
        return WebhookArgs::class;
    }

    public function authorize(Authenticatable $user, Data $args): void
    {
        $this->gate($user)->authorize('viewAny', WebhookEndpoint::class);
    }

    /**
     * Every endpoint's deliveries share the table, and a busy app writes to
     * it on every audited change.
     *
     * @return array<int, Dep>
     */
    public function reads(Data $args): array
    {
        return [Dep::eq('webhook_deliveries', 'webhook_endpoint_id', $args->id)];
    }

    /**
     * @return Collection<int, WebhookDeliveryData>
     */
    public function handle(Data $args): mixed
    {
        return WebhookDeliveryData::collect(
            WebhookDelivery::query()
                ->where('webhook_endpoint_id', $args->id)
                ->orderByDesc('id')
                ->limit(25)
                ->get(),
        );
    }
}
