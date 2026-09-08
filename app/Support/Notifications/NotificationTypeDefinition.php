<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Enums\NotificationChannel;
use App\Notifications\Attributes\NotificationType;

/**
 * One configurable notification, as the registry resolved it from the
 * attribute. Channel lists are enums by the time they leave here, so nothing
 * downstream has to guess whether a string is a channel the app knows.
 */
final class NotificationTypeDefinition
{
    /**
     * @param  class-string  $class
     * @param  array<int, NotificationChannel>  $channels  the ladder, in escalation order
     * @param  array<int, NotificationChannel>  $default
     * @param  array<int, NotificationChannel>  $required
     */
    public function __construct(
        public readonly string $key,
        public readonly string $class,
        public readonly string $group,
        public readonly array $channels,
        public readonly array $default,
        public readonly array $required,
    ) {}

    /**
     * @param  class-string  $class
     */
    public static function fromAttribute(string $class, NotificationType $attribute): self
    {
        $channels = NotificationChannel::fromValues($attribute->channels);

        // A default or a requirement outside the declared ladder would be a
        // preference the sender can never honour, so it is dropped here
        // rather than surfacing as a switch that does nothing.
        $within = static fn (array $values): array => array_values(array_filter(
            NotificationChannel::fromValues($values),
            static fn (NotificationChannel $channel): bool => in_array($channel, $channels, true),
        ));

        return new self(
            key: $attribute->key,
            class: $class,
            group: $attribute->group,
            channels: $channels,
            default: $within($attribute->default),
            required: $within($attribute->required),
        );
    }

    public function offers(NotificationChannel $channel): bool
    {
        return in_array($channel, $this->channels, true);
    }

    public function requires(NotificationChannel $channel): bool
    {
        return in_array($channel, $this->required, true);
    }

    /**
     * The channels on for an account that has never opened the preferences
     * screen. Required rungs are always in, whatever the defaults say.
     *
     * @return array<int, NotificationChannel>
     */
    public function defaultChannels(): array
    {
        return array_values(array_filter(
            $this->channels,
            fn (NotificationChannel $channel): bool => in_array($channel, $this->default, true)
                || $this->requires($channel),
        ));
    }
}
