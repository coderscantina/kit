<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Enums\NotificationChannel;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * The `database` driver, with the escalation plan written into the row.
 *
 * A row that only says what arrived cannot answer "what else was supposed to
 * happen, and did it?". Storing the ladder at creation time makes the inbox
 * table the whole record of a notification: what was planned, what has been
 * delivered so far, and whether the person ever looked.
 *
 * Written into the insert rather than added by a listener afterwards, so the
 * row is complete the first time subscribers see it and no push carries a
 * half-built notification.
 */
final class InboxChannel extends DatabaseChannel
{
    /**
     * @return array<string, mixed>
     */
    protected function buildPayload($notifiable, Notification $notification)
    {
        $payload = parent::buildPayload($notifiable, $notification);

        $ladder = $notification instanceof AppNotification && $notifiable instanceof User
            ? NotificationChannel::toValues($notification->ladder($notifiable))
            : [];

        return [
            ...$payload,
            'channels' => $ladder,
            'deliveries' => ['database' => now()->toIso8601String()],
        ];
    }
}
