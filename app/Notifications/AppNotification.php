<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationChannel;
use App\Models\Notification as InboxNotification;
use App\Models\User;
use App\Support\Notifications\ChannelResolver;
use App\Support\Notifications\NotificationRegistry;
use App\Support\Notifications\NotificationTypeDefinition;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use RuntimeException;

/**
 * Base for every notification a user can configure.
 *
 * A subclass declares #[NotificationType], fills in toArray() for the inbox
 * row, and adds a toMail()/toWebPush()/toSms() for each rung its ladder
 * names. Everything else — which channels go out, in what order, how long
 * apart, and when to stop — is decided here and cannot be got wrong per
 * notification.
 *
 * The ladder is not a fan-out. `database` always goes out; so does the first
 * enabled rung. Every later rung is queued with a delay and checks the inbox
 * row before it sends, so a person who saw the push never gets the mail. That
 * is the whole escalation mechanism: Laravel's own per-channel `withDelay()`
 * and `shouldSend()`, with the inbox row as the shared signal.
 *
 * Delivery is queued. On the `sync` queue connection a delay is not a delay,
 * so every rung fires at once; that is a local-development quirk worth
 * knowing rather than a bug. See docs/notifications.md.
 */
abstract class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * What the inbox row carries, and what the client renders. Keep it to
     * scalars and small arrays: it is stored as JSON, pushed over a socket,
     * and read months later, when the models it came from may be gone.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(object $notifiable): array;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            // No inbox, no preferences, no ladder: someone addressed by email
            // alone gets the one channel that can reach them.
            return ['mail'];
        }

        return ['database', ...NotificationChannel::toValues($this->ladder($notifiable))];
    }

    /** The stable key, which is what the `type` column stores. */
    public function databaseType(object $notifiable): string
    {
        return $this->definition()->key;
    }

    /**
     * Rung 0 rides along with the inbox row. Rung n waits n intervals, and
     * only sends if nobody has looked at the notification by then.
     */
    public function withDelay(object $notifiable, ?string $channel = null): ?DateTimeInterface
    {
        $rung = $this->rung($notifiable, $channel);
        $minutes = (int) config('notifications.escalation_minutes', 5);

        if ($rung < 1 || $minutes < 1) {
            return null;
        }

        return now()->addMinutes($rung * $minutes);
    }

    /**
     * The escalation guard. A later rung sends only while the inbox row is
     * still unseen and unarchived; the row missing means the database channel
     * has not landed yet, and a rung that arrives before the row it depends
     * on is one the user has certainly not seen.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        if ($this->rung($notifiable, $channel) < 1) {
            return true;
        }

        $row = InboxNotification::query()->find($this->id);

        return $row === null || ($row->read_at === null && $row->archived_at === null);
    }

    /**
     * The ladder this notification was sent with, stored on the inbox row by
     * RecordNotificationDelivery so the row says what was planned as well as
     * what happened.
     *
     * @return array<int, NotificationChannel>
     */
    public function ladder(User $user): array
    {
        return app(ChannelResolver::class)->ladder($user, $this->definition());
    }

    public function definition(): NotificationTypeDefinition
    {
        $definition = app(NotificationRegistry::class)->forClass(static::class);

        if ($definition === null) {
            throw new RuntimeException(static::class.' extends AppNotification but carries no #[NotificationType] attribute.');
        }

        return $definition;
    }

    /**
     * Where a channel sits on this user's ladder. -1 for `database` and for
     * anything not on it, which both read as "send now".
     */
    private function rung(object $notifiable, ?string $channel): int
    {
        if ($channel === null || $channel === 'database' || ! $notifiable instanceof User) {
            return -1;
        }

        $index = array_search($channel, NotificationChannel::toValues($this->ladder($notifiable)), true);

        return $index === false ? -1 : $index;
    }
}
