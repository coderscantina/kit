<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Attributes\NotificationType;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Sent to whoever sent the invite, once it has been accepted.
 *
 * Push first, mail only if the inviter never looked: this is good news, not
 * urgent news, and an email about it five minutes after the browser already
 * said so is noise.
 */
#[NotificationType(
    key: 'people.invite_accepted',
    group: 'people',
    channels: ['push', 'mail'],
    default: ['push', 'mail'],
)]
class InviteAcceptedNotification extends AppNotification
{
    public function __construct(
        private readonly string $name,
        private readonly string $email,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line($this->body())
            ->action(__('notifications.inviteAccepted.action'), url('/users'));
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->body($this->body())
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->data(['url' => '/users']);
    }

    private function title(): string
    {
        return __('notifications.inviteAccepted.title');
    }

    private function body(): string
    {
        return __('notifications.inviteAccepted.body', ['name' => $this->name, 'email' => $this->email]);
    }
}
