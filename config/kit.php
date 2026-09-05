<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Install state
    |--------------------------------------------------------------------------
    |
    | kit:setup keys its idempotency on this file. The registration latch is a
    | sibling marker: once an account exists it is written and self-registration
    | stays closed even if the database is unreachable or the last account is
    | deleted. Tests point both into storage/app/testing.
    |
    */

    'setup' => [
        'state_path' => storage_path('app/setup/install-state.json'),
        'registration_closed_path' => storage_path('app/setup/registration-closed'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ambient container bindings
    |--------------------------------------------------------------------------
    |
    | Keys that middleware or jobs may bind on the container for the duration of
    | one request or job (a tenant, a locale override). QueuedJob snapshots and
    | restores them around execute(), and config/octane.php flushes them before
    | every request. A binding missing from this list leaks between requests.
    |
    */

    'ambient_bindings' => [],

    /*
    |--------------------------------------------------------------------------
    | Password confirmation window
    |--------------------------------------------------------------------------
    */

    'password_confirmation_minutes' => (int) env('PASSWORD_CONFIRMATION_MINUTES', 15),

    'totp_grace_minutes' => (int) env('TOTP_GRACE_MINUTES', 30),

    'invite_expiry_days' => (int) env('INVITE_EXPIRY_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Required environment
    |--------------------------------------------------------------------------
    |
    | config:validate refuses to boot a production container when any of these
    | is unset. Local and testing environments only need the "always" list.
    |
    */

    'required_env' => [
        'always' => ['APP_NAME', 'APP_ENV', 'APP_KEY', 'APP_URL', 'DB_CONNECTION'],
        'production' => [
            'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
            'REDIS_HOST',
            'REVERB_APP_ID', 'REVERB_APP_KEY', 'REVERB_APP_SECRET', 'REVERB_HOST',
            'MAIL_MAILER', 'MAIL_FROM_ADDRESS',
        ],
    ],

];
