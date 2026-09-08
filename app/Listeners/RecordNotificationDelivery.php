<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Notification as InboxNotification;
use App\Notifications\AppNotification;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Stamps the inbox row when a rung of the ladder actually delivers.
 *
 * Without this the row knows what was planned and nothing about what
 * happened, so "why did this person get an SMS?" has no answer. With it,
 * `deliveries` is a channel-to-timestamp map that survives the queue, the
 * provider and the person.
 *
 * The database rung stamps itself at insert time (InboxChannel), so it is
 * skipped here.
 */
final class RecordNotificationDelivery
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel === 'database' || ! $event->notification instanceof AppNotification) {
            return;
        }

        InboxNotification::query()
            ->find($event->notification->id)
            ?->recordDelivery($event->channel);
    }
}
