<?php

declare(strict_types=1);

return [

    'channels' => [
        'push' => 'Browser push',
        'mail' => 'Email',
        'sms' => 'SMS',
    ],

    'events' => [
        'password_changed' => 'Your password was changed.',
        'email_changed' => 'The email address on your account was changed.',
        'two_factor_enabled' => 'Two-factor authentication was turned on.',
        'two_factor_disabled' => 'Two-factor authentication was turned off.',
        'backup_codes_regenerated' => 'Your two-factor backup codes were replaced.',
        'social_linked' => 'A sign-in provider was linked to your account.',
        'social_unlinked' => 'A sign-in provider was unlinked from your account.',
        'token_created' => 'An API access token was created for your account.',
    ],

    'security' => [
        'title' => 'Security alert on :app',
        'where' => 'From :device, IP :ip.',
        'action' => 'Review your security settings',
        'disclaimer' => 'If this was you, nothing needs doing.',
        'sms_tail' => 'If this was not you, secure your account now.',
    ],

    'inviteAccepted' => [
        'title' => 'Invitation accepted',
        'body' => ':name (:email) accepted your invitation.',
        'action' => 'See the team',
    ],

    'phone' => [
        'code' => ':code is your :app verification code.',
        'invalid' => 'Enter the number in international format, starting with a plus and the country code.',
        'invalid_code' => 'That code is not right, or it has expired. Ask for a new one.',
        'too_soon' => 'Wait :seconds more seconds before asking for another code.',
    ],

];
