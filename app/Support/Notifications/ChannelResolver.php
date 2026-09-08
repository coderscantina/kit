<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Enums\NotificationChannel;
use App\Models\User;
use App\Support\FeatureGate;

/**
 * Turns a notification type and a user into the ordered ladder that will
 * actually be attempted.
 *
 * Three filters, in this order:
 *
 *   1. the type's own `channels` — the ladder it declared, and its order
 *   2. the user's preference, with the type's `required` rungs forced in
 *   3. deliverability — a push rung needs a subscribed browser, an SMS rung
 *      needs a verified phone, and both need the feature to be configured
 *
 * The third filter is why a preference can be switched on before the
 * prerequisite exists: turning on SMS and then verifying a phone works in
 * either order, and neither step produces a dead rung in between.
 *
 * Preferences are read once per user per process. A send fans out over one
 * user at a time, so this is the difference between one query and one query
 * per channel.
 */
final class ChannelResolver
{
    /** @var array<string, array<string, array<int, string>>> */
    private array $preferences = [];

    /**
     * The ladder, in escalation order. Index 0 goes out with the inbox row;
     * every later index waits for its delay and for the notification to still
     * be unseen.
     *
     * @return array<int, NotificationChannel>
     */
    public function ladder(User $user, NotificationTypeDefinition $definition): array
    {
        return array_values(array_filter(
            $this->enabled($user, $definition),
            fn (NotificationChannel $channel): bool => $this->deliverable($user, $channel),
        ));
    }

    /**
     * What the user asked for, before deliverability. This is what the
     * preferences screen shows: the switches reflect the choice, not whether
     * the choice can be honoured right now.
     *
     * @return array<int, NotificationChannel>
     */
    public function enabled(User $user, NotificationTypeDefinition $definition): array
    {
        $chosen = $this->chosen($user, $definition->key);

        if ($chosen === null) {
            return $definition->defaultChannels();
        }

        return array_values(array_filter(
            $definition->channels,
            fn (NotificationChannel $channel): bool => in_array($channel->value, $chosen, true)
                || $definition->requires($channel),
        ));
    }

    public function deliverable(User $user, NotificationChannel $channel): bool
    {
        return match ($channel) {
            NotificationChannel::Mail => true,
            NotificationChannel::Push => FeatureGate::pushEnabled() && $user->pushSubscriptions()->exists(),
            NotificationChannel::Sms => FeatureGate::smsEnabled() && $user->hasVerifiedPhone(),
        };
    }

    /**
     * Drop a user's memoised preferences. Called after a write, so the next
     * send in the same request sees the new choice.
     */
    public function forget(User $user): void
    {
        unset($this->preferences[$user->id]);
    }

    /**
     * The stored choice, or null when the user has never chosen for this
     * type. Null and an empty array mean different things: silence takes the
     * type's defaults, an empty array is a deliberate "inbox only".
     *
     * @return array<int, string>|null
     */
    private function chosen(User $user, string $type): ?array
    {
        $this->preferences[$user->id] ??= $user->notificationPreferences()
            ->pluck('channels', 'type')
            ->all();

        return $this->preferences[$user->id][$type] ?? null;
    }
}
