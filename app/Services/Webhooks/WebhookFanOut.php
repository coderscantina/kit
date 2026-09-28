<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Models\AuditEntry;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\Records;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns an audit entry into one pending delivery per endpoint listening for
 * it. The event is `<table>.<created|updated|deleted|restored>`, the name the
 * client already uses for the record, and the payload carries the same
 * redacted changes the history shows, never the whole row.
 */
class WebhookFanOut
{
    public function dispatch(Model $subject, AuditEntry $entry): void
    {
        $event = Records::typeOf($subject).'.'.$entry->event;

        $endpoints = WebhookEndpoint::query()
            ->where('active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint): bool => $endpoint->listensTo($event));

        foreach ($endpoints as $endpoint) {
            WebhookDelivery::query()->create([
                'webhook_endpoint_id' => $endpoint->id,
                'event' => $event,
                'payload' => [
                    'type' => $event,
                    'timestamp' => $entry->created_at->toIso8601String(),
                    'data' => [
                        'object' => Records::typeOf($subject),
                        'id' => $entry->subject_id,
                        'changes' => $entry->changes,
                        'actorId' => $entry->actor_id,
                    ],
                ],
                'status' => WebhookDelivery::PENDING,
            ]);
        }
    }
}
