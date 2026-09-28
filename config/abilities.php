<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Ability registry
|--------------------------------------------------------------------------
|
| Every ability the application knows, as `<resource>.<verb>` strings, and the
| roles that grant them. This is the one place a new resource is declared;
| `make:feature` appends to the marker lines below. Roles are synced into the
| roles table by `kit:setup` (and `authorization:sync-roles`), so editing this
| file and re-running setup is how a role changes.
|
| Abilities carry no scope on purpose. When a tenant scope arrives, can() gains
| a scope argument and these strings stay as they are.
|
*/

return [

    'abilities' => [
        'app.access',
        'users.view',
        'users.manage',
        'invites.view',
        'invites.manage',
        'roles.manage',
        'ai.use',
        'webhooks.manage',
        // kit:abilities
    ],

    'roles' => [
        'owner' => [
            'name' => 'Owner',
            'level' => 300,
            'abilities' => [
                'app.access',
                'users.view',
                'users.manage',
                'invites.view',
                'invites.manage',
                'roles.manage',
                'ai.use',
                'webhooks.manage',
                // kit:role:owner
            ],
        ],
        'admin' => [
            'name' => 'Admin',
            'level' => 200,
            'abilities' => [
                'app.access',
                'users.view',
                'users.manage',
                'invites.view',
                'invites.manage',
                'ai.use',
                'webhooks.manage',
                // kit:role:admin
            ],
        ],
        'member' => [
            'name' => 'Member',
            'level' => 100,
            'abilities' => [
                'app.access',
                // kit:role:member
            ],
        ],
    ],

    'cache_ttl_seconds' => 3600,

];
