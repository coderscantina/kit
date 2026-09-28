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

    'email_change_expiry_hours' => (int) env('EMAIL_CHANGE_EXPIRY_HOURS', 2),

    /*
    |--------------------------------------------------------------------------
    | Avatars
    |--------------------------------------------------------------------------
    |
    | Avatars are stored on a private disk and read back through
    | GET /api/users/{user}/avatar, so they stay behind the same session the
    | rest of the app needs. Point the disk at s3 to move them off the box.
    | The client crops and resizes before upload; these limits are the backstop.
    |
    */

    'avatars' => [
        'disk' => env('AVATAR_DISK', 'local'),
        'max_kilobytes' => (int) env('AVATAR_MAX_KILOBYTES', 2048),
        'max_dimension' => (int) env('AVATAR_MAX_DIMENSION', 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit trail
    |--------------------------------------------------------------------------
    |
    | Entries older than the retention are pruned daily by model:prune; unset
    | keeps them forever. The history query shows the newest entries only.
    |
    */

    'audit' => [
        'retention_days' => env('AUDIT_RETENTION_DAYS'),
        'history_limit' => (int) env('AUDIT_HISTORY_LIMIT', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | Files on records live on a private disk and are read back through the
    | app, like avatars. `extensions` is an allow list checked against the
    | bytes, not the filename. Keep max_kilobytes under upload_max_filesize
    | in docker/php.ini. Images get a thumbnail on the queue.
    |
    */

    'attachments' => [
        'disk' => env('ATTACHMENT_DISK', 'local'),
        'max_kilobytes' => (int) env('ATTACHMENT_MAX_KILOBYTES', 20480),
        'extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp',
            'pdf', 'txt', 'csv', 'json', 'md',
            'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods',
            'zip',
        ],
        'thumbnail_size' => 480,
        // A decoded image costs width x height x 4 bytes of memory; past this
        // many pixels the upload keeps no thumbnail rather than risk the worker.
        'thumbnail_max_pixels' => 40_000_000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | allow_private_targets lets an endpoint point at localhost or a private
    | network, for a receiver on the developer's machine. Leave it off
    | anywhere else: it is what stops a webhook from reaching internal
    | services and the cloud metadata endpoint.
    |
    */

    'webhooks' => [
        'allow_private_targets' => (bool) env('WEBHOOKS_ALLOW_PRIVATE_TARGETS', false),
        'timeout_seconds' => (int) env('WEBHOOKS_TIMEOUT_SECONDS', 10),
        'retention_days' => (int) env('WEBHOOKS_RETENTION_DAYS', 30),
    ],

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
