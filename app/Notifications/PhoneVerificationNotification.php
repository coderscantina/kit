<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\Sms\SmsMessage;
use Illuminate\Notifications\Notification;

/**
 * The code that proves a phone number belongs to the person typing it in.
 *
 * Deliberately not an AppNotification: it carries no #[NotificationType], so
 * it never appears in the preferences screen and cannot be switched off. A
 * verification code the user opted out of would be a flow that silently
 * stops working.
 *
 * Sent to the number under verification rather than the account's route,
 * which is the one case where an unverified number is the right destination.
 */
class PhoneVerificationNotification extends Notification
{
    public function __construct(
        private readonly string $phone,
        private readonly string $code,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['sms'];
    }

    public function toSms(object $notifiable): SmsMessage
    {
        return new SmsMessage($this->phone, __('notifications.phone.code', [
            'code' => $this->code,
            'app' => (string) config('app.name'),
        ]));
    }
}
