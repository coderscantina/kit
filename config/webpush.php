<?php

declare(strict_types=1);
use NotificationChannels\WebPush\PushSubscription;

/*
|--------------------------------------------------------------------------
| Web push (VAPID)
|--------------------------------------------------------------------------
|
| Generate a key pair once with `php artisan webpush:vapid` and keep it: the
| public key is baked into every subscription a browser has already made, so
| rotating it silently orphans every device that opted in.
|
| Without VAPID_PUBLIC_KEY the app has no push feature at all — the account
| page hides the switch rather than offering one that can only fail. See
| docs/push-notifications.md.
|
*/

return [

    'vapid' => [
        'subject' => env('VAPID_SUBJECT', env('APP_URL', 'http://localhost')),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'pem_file' => env('VAPID_PEM_FILE'),
    ],

    'model' => PushSubscription::class,

    'table_name' => 'push_subscriptions',

    'database_connection' => env('DB_CONNECTION', 'mysql'),

    'client_options' => [],

    /** Off supports Firefox on Android with the v1 endpoint. */
    'automatic_padding' => env('WEBPUSH_AUTOMATIC_PADDING', true),

];
