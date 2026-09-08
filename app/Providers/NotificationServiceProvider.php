<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\RecordNotificationDelivery;
use App\Notifications\Channels\InboxChannel;
use App\Notifications\Channels\SmsChannel;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use App\Support\Notifications\ChannelResolver;
use App\Support\Notifications\NotificationRegistry;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use NotificationChannels\WebPush\WebPushChannel;
use RuntimeException;

/**
 * Wires the notification layer: the type registry, the channel ladder, the
 * SMS seam, and the channel names the rest of the app uses.
 *
 * Channels are registered under plain names — `push`, `sms` — rather than
 * class strings. The names are what a preference stores, what a delay is
 * keyed on and what a delivery record says, so having one vocabulary end to
 * end is worth three lines here.
 */
class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationRegistry::class, fn (): NotificationRegistry => new NotificationRegistry(
            path: (string) config('notifications.discovery.path', app_path('Notifications')),
            namespace: (string) config('notifications.discovery.namespace', 'App\\Notifications'),
        ));

        $this->app->singleton(ChannelResolver::class);

        $this->app->singleton(SmsSender::class, function (): SmsSender {
            $driver = (string) config('sms.driver');
            /** @var array<string, mixed> $options */
            $options = (array) config("sms.drivers.{$driver}", []);

            return match ($driver) {
                'log' => new LogSmsSender(isset($options['channel']) ? (string) $options['channel'] : null),
                // Reaching here means config/sms.php names a driver nobody
                // bound. Failing loudly beats dropping messages silently.
                default => throw new RuntimeException("No SMS sender is bound for driver '{$driver}'."),
            };
        });
    }

    public function boot(): void
    {
        Notification::resolved(function (ChannelManager $manager): void {
            // The inbox row carries the escalation plan, so `database` is our
            // channel rather than the framework's.
            $manager->extend('database', fn ($app) => $app->make(InboxChannel::class));
            $manager->extend('push', fn ($app) => $app->make(WebPushChannel::class));
            $manager->extend('sms', fn ($app) => $app->make(SmsChannel::class));
        });

        Event::listen(NotificationSent::class, RecordNotificationDelivery::class);
    }
}
