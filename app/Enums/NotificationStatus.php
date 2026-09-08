<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a notification sits in the inbox.
 *
 * Three states, derived from two timestamps rather than stored: `read_at` is
 * the column Laravel's own notification model ships, and the app calls it
 * "seen" because that is what it means here — the person has laid eyes on it,
 * which is also the signal that stops the escalation ladder.
 */
enum NotificationStatus: string
{
    case Unseen = 'unseen';
    case Seen = 'seen';
    case Archived = 'archived';
}
