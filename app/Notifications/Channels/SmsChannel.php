<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Services\Sms\SmsMessage;
use App\Services\Sms\SmsSender;
use App\Support\FeatureGate;
use Illuminate\Notifications\Notification;

/**
 * Delivers a notification's toSms() through the configured sender.
 *
 * The destination is the notifiable's route — `User::routeNotificationForSms()`,
 * which is null until the number has been verified, because an unverified
 * number is not a number this app is willing to text.
 *
 * A message that names its own recipient wins, and exactly one notification
 * does: the verification code itself, which by definition goes to a number
 * that has no route yet.
 */
final class SmsChannel
{
    public function __construct(private readonly SmsSender $sender) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! FeatureGate::smsEnabled() || ! method_exists($notification, 'toSms')) {
            return;
        }

        $message = $notification->toSms($notifiable);
        $message = $message instanceof SmsMessage ? $message : new SmsMessage('', (string) $message);

        if ($message->to === '') {
            $route = $notifiable->routeNotificationFor('sms', $notification);

            if (! is_string($route) || $route === '') {
                return;
            }

            $message = $message->to($route);
        }

        if (trim($message->content) === '') {
            return;
        }

        $this->sender->send($message);
    }
}
