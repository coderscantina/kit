<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * The one notification the account page can send to itself, so a user can
 * check that the switch they just turned on actually reaches this device.
 */
class TestPushNotification extends Notification
{
    /**
     * @return array<int, class-string>
     */
    public function via(mixed $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(mixed $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title(__('push.test_title', ['app' => config('app.name')]))
            ->body(__('push.test_body'))
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->tag('kit-test')
            ->data(['url' => '/account/security']);
    }
}
