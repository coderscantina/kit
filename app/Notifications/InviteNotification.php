<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invite;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Carries only what the mail needs (never the model): the accept URL with
 * the one-time token and the inviter's display name.
 */
class InviteNotification extends Notification
{
    private readonly string $url;

    private readonly ?string $invitedBy;

    private readonly string $expiresAt;

    public function __construct(Invite $invite, string $token)
    {
        $this->url = url("/invites/{$invite->id}?token={$token}");
        $this->invitedBy = $invite->inviter?->name;
        $this->expiresAt = $invite->expires_at->toDayDateTimeString();
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
            ->subject(__('auth.invite_subject', ['app' => $app]))
            ->line($this->invitedBy !== null
                ? __('auth.invite_line_by', ['name' => $this->invitedBy, 'app' => $app])
                : __('auth.invite_line', ['app' => $app]))
            ->action(__('auth.invite_action'), $this->url)
            ->line(__('auth.invite_expires', ['date' => $this->expiresAt]));
    }
}
