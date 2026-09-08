<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\NotificationChannel;
use App\Models\User;
use App\Support\FeatureGate;
use App\Support\Notifications\ChannelResolver;
use App\Support\Notifications\NotificationRegistry;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Everything the notification settings screen renders: the channel columns,
 * the type rows, the phone the SMS column depends on, and how long a rung
 * waits before the next one fires.
 *
 * One payload rather than three endpoints, because the screen is meaningless
 * with only part of it: a switch without its channel's deliverability is a
 * promise the app may not be able to keep.
 */
#[TypeScript]
final class NotificationSettingsData extends Data
{
    /**
     * @param  array<int, NotificationChannelData>  $channels
     * @param  array<int, NotificationTypeData>  $types
     */
    public function __construct(
        public array $channels,
        public array $types,
        public PhoneData $phone,
        public int $escalationMinutes,
    ) {}

    public static function forUser(User $user): self
    {
        $registry = app(NotificationRegistry::class);
        $resolver = app(ChannelResolver::class);

        $channels = [];

        foreach (array_keys((array) config('notifications.channels', [])) as $key) {
            $channel = NotificationChannel::tryFrom((string) $key);

            if ($channel === null) {
                continue;
            }

            $channels[] = new NotificationChannelData(
                key: $channel->value,
                available: self::available($channel),
                deliverable: $resolver->deliverable($user, $channel),
            );
        }

        $types = [];

        foreach ($registry->all() as $definition) {
            $types[] = new NotificationTypeData(
                key: $definition->key,
                group: $definition->group,
                channels: NotificationChannel::toValues($definition->channels),
                required: NotificationChannel::toValues($definition->required),
                enabled: NotificationChannel::toValues($resolver->enabled($user, $definition)),
            );
        }

        return new self(
            channels: $channels,
            types: $types,
            phone: PhoneData::fromModel($user),
            escalationMinutes: (int) config('notifications.escalation_minutes', 5),
        );
    }

    /** Configured for this installation, regardless of this account. */
    private static function available(NotificationChannel $channel): bool
    {
        return match ($channel) {
            NotificationChannel::Mail => true,
            NotificationChannel::Push => FeatureGate::pushEnabled(),
            NotificationChannel::Sms => FeatureGate::smsEnabled(),
        };
    }
}
