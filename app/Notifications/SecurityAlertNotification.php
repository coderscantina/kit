<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SecurityEvent;
use App\Notifications\Attributes\NotificationType;
use App\Services\Sms\SmsMessage;
use App\Support\UserAgent;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Something changed on the account that the owner would want to notice: a new
 * password, two-factor switched off, the address moved.
 *
 * Mail is required. A person who turned off every notification still has to
 * hear that their password changed, because the one case that matters is the
 * one where somebody else changed it.
 */
#[NotificationType(
    key: 'security.alert',
    group: 'security',
    channels: ['push', 'mail', 'sms'],
    default: ['push', 'mail'],
    required: ['mail'],
)]
class SecurityAlertNotification extends AppNotification
{
    /**
     * The events worth interrupting someone for. A sign-in is not on the list
     * on purpose: it happens every day, and a notification that arrives every
     * day is one nobody reads on the day it matters.
     *
     * @var array<int, string>
     */
    public const array EVENTS = [
        SecurityEvent::PASSWORD_CHANGED,
        SecurityEvent::EMAIL_CHANGED,
        SecurityEvent::TWO_FACTOR_ENABLED,
        SecurityEvent::TWO_FACTOR_DISABLED,
        SecurityEvent::BACKUP_CODES_REGENERATED,
        SecurityEvent::SOCIAL_LINKED,
        SecurityEvent::SOCIAL_UNLINKED,
        SecurityEvent::TOKEN_CREATED,
    ];

    private readonly string $device;

    public function __construct(
        private readonly string $event,
        private readonly ?string $ipAddress,
        ?string $userAgent,
    ) {
        $this->device = UserAgent::describe($userAgent);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->event,
            'ipAddress' => $this->ipAddress,
            'device' => $this->device,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line($this->body())
            ->line(__('notifications.security.where', ['device' => $this->device, 'ip' => $this->ipAddress ?? '—']))
            ->action(__('notifications.security.action'), url('/account/security'))
            ->line(__('notifications.security.disclaimer'));
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->body($this->body())
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            // Replaces its predecessor: two alerts in a row are one story.
            ->tag('security-alert')
            ->data(['url' => '/account/security']);
    }

    public function toSms(object $notifiable): SmsMessage
    {
        return new SmsMessage('', $this->title().' '.__('notifications.security.sms_tail'));
    }

    private function title(): string
    {
        return __('notifications.security.title', ['app' => (string) config('app.name')]);
    }

    private function body(): string
    {
        return __('notifications.events.'.$this->event);
    }
}
