<?php

declare(strict_types=1);

namespace App\Queries\Webhooks;

use App\Data\WebhookEventsData;
use App\Models\AuditEntry;
use App\Models\Concerns\Auditable;
use App\Models\WebhookEndpoint;
use App\Support\Records;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kit\Reactive\Attributes\ReactiveQuery;
use Kit\Reactive\NoArgs;
use Kit\Reactive\Query;
use Spatie\LaravelData\Data;

/**
 * The events an endpoint can subscribe to. A model that starts using
 * Auditable adds its events here without being registered.
 *
 * @extends Query<NoArgs>
 */
#[ReactiveQuery('webhooks.events', result: WebhookEventsData::class)]
final class ListWebhookEvents extends Query
{
    public static function args(): string
    {
        return NoArgs::class;
    }

    public function authorize(Authenticatable $user, Data $args): void
    {
        $this->gate($user)->authorize('viewAny', WebhookEndpoint::class);
    }

    public function handle(Data $args): WebhookEventsData
    {
        $events = [];

        foreach (Records::types(Auditable::class) as $type => $class) {
            $changes = [AuditEntry::CREATED, AuditEntry::UPDATED, AuditEntry::DELETED];

            // Only a soft-deleting model is ever restored.
            if (in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
                $changes[] = AuditEntry::RESTORED;
            }

            foreach ($changes as $change) {
                $events[] = "{$type}.{$change}";
            }
        }

        return new WebhookEventsData($events);
    }
}
