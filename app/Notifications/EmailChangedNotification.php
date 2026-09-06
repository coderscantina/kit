<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The receipt, sent to the address that just lost the account.
 */
class EmailChangedNotification extends Notification
{
    public function __construct(
        private readonly string $newEmail,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('auth.email_changed_subject', ['app' => (string) config('app.name')]))
            ->line(__('auth.email_changed_line', ['email' => $this->newEmail]))
            ->line(__('auth.email_change_notice_warning'));
    }
}
