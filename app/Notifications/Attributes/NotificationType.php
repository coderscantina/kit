<?php

declare(strict_types=1);

namespace App\Notifications\Attributes;

use Attribute;

/**
 * Marks a notification as one a user can configure, and declares how it
 * escalates.
 *
 * The attribute is the whole registration: `NotificationRegistry` scans
 * app/Notifications for it, the preferences screen is built from what it
 * finds, and the `key` is what the `type` column stores. A notification
 * without the attribute is transactional (password reset, email
 * confirmation) and stays outside all of this on purpose.
 *
 * `channels` is an ordered ladder, not a set. The first enabled rung goes out
 * with the inbox row; each later rung fires only if the notification is still
 * unseen when its delay elapses. SMS is never in a ladder unless a type asks
 * for it by name.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class NotificationType
{
    /**
     * @param  string  $key  Stable identifier, stored in `type` and sent to the client.
     * @param  string  $group  Preference screen grouping; a translation key suffix.
     * @param  array<int, string>  $channels  The ladder, in escalation order.
     * @param  array<int, string>  $default  Rungs enabled for an account that has not chosen.
     * @param  array<int, string>  $required  Rungs the user cannot switch off.
     */
    public function __construct(
        public string $key,
        public string $group = 'general',
        public array $channels = ['push', 'mail'],
        public array $default = ['push', 'mail'],
        public array $required = [],
    ) {}
}
