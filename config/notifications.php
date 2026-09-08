<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
|
| The inbox is always written: every notification with a #[NotificationType]
| attribute lands in the `notifications` table first, and everything else is
| an attempt to get the person's attention somewhere else.
|
| Those attempts are a ladder, not a fan-out. The type declares the rungs it
| supports and the user's preferences decide which of them are live; rung 0
| goes out with the row, and each later rung only fires if the notification
| is still unseen when its delay elapses. See docs/notifications.md.
|
*/

return [

    /*
    | The channels the app knows, in the order a preferences screen shows
    | them. `label` is a translation key. A channel that is not deliverable
    | for a user (no push subscription, no verified phone) is skipped at send
    | time, so a preference can be set before the prerequisite exists.
    */
    'channels' => [
        'push' => ['label' => 'notifications.channels.push'],
        'mail' => ['label' => 'notifications.channels.mail'],
        'sms' => ['label' => 'notifications.channels.sms'],
    ],

    /*
    | Minutes between two rungs of the ladder. Rung 0 is immediate; rung n
    | fires n * this many minutes later, and only while the notification is
    | still unseen. Zero turns escalation off and sends every enabled channel
    | at once.
    */
    'escalation_minutes' => (int) env('NOTIFICATIONS_ESCALATION_MINUTES', 5),

    /*
    | Where the discovery scan looks for #[NotificationType] classes. The
    | preferences screen is built from what it finds, so a notification that
    | is not here cannot be configured — which is the right answer for the
    | transactional mail (password reset, email confirmation) that a user
    | must not be able to turn off.
    */
    'discovery' => [
        'path' => app_path('Notifications'),
        'namespace' => 'App\\Notifications',
    ],

    /*
    | Archived notifications older than this are deleted by
    | `notifications:prune`, scheduled daily. Null keeps them forever.
    */
    'retention_days' => (int) env('NOTIFICATIONS_RETENTION_DAYS', 90),

    /*
    | How many rows the inbox query returns at most. The bell shows a slice of
    | this; the page shows all of it. A subscribed result over 8 KB costs the
    | client an extra round trip, so this stays small on purpose.
    */
    'inbox_limit' => 50,

];
