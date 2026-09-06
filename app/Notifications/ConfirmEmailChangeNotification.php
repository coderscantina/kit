<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\EmailChange;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Goes to the address being moved to. Opening the link is what proves the
 * inbox belongs to the person asking, so this mail carries the only token.
 */
class ConfirmEmailChangeNotification extends Notification
{
    private readonly string $url;

    private readonly string $expiresAt;

    public function __construct(EmailChange $change, string $token)
    {
        $this->url = url("/account/email/confirm?id={$change->id}&token={$token}");
        $this->expiresAt = $change->expires_at->toDayDateTimeString();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = (string) config('app.name');

        return (new MailMessage)
            ->subject(__('auth.email_change_subject', ['app' => $app]))
            ->line(__('auth.email_change_line', ['app' => $app]))
            ->action(__('auth.email_change_action'), $this->url)
            ->line(__('auth.email_change_expires', ['date' => $this->expiresAt]))
            ->line(__('auth.email_change_ignore'));
    }
}
