<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

/**
 * Points the reset link at the SPA route instead of a Blade page.
 */
class ResetPasswordNotification extends ResetPassword
{
    public function __construct(string $token)
    {
        parent::__construct($token);

        $this->createUrlUsing(fn (object $notifiable, string $token) => url('/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ])));
    }
}
