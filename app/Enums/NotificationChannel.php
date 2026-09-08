<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The delivery channels a configurable notification can use.
 *
 * `Database` is deliberately absent: the inbox row is not a choice, it is
 * where every notification lands before anything else is attempted.
 */
enum NotificationChannel: string
{
    case Push = 'push';
    case Mail = 'mail';
    case Sms = 'sms';

    /**
     * @param  array<int, string>  $values
     * @return array<int, self>
     */
    public static function fromValues(array $values): array
    {
        return array_values(array_filter(array_map(self::tryFrom(...), $values)));
    }

    /**
     * @param  array<int, self>  $channels
     * @return array<int, string>
     */
    public static function toValues(array $channels): array
    {
        return array_map(static fn (self $channel): string => $channel->value, $channels);
    }
}
