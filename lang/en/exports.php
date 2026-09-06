<?php

declare(strict_types=1);

return [

    'people' => [
        'title' => 'People',
        // Order is the column order in the file.
        'columns' => [
            'name' => 'Name',
            'email' => 'Email',
            'role' => 'Role',
            'status' => 'Status',
            'kind' => 'Type',
            'joined' => 'Joined',
            'expires' => 'Invitation expires',
        ],
        'states' => [
            'active' => 'Active',
            'pending' => 'Invited',
            'expired' => 'Expired',
        ],
        'kinds' => [
            'user' => 'Account',
            'invite' => 'Invitation',
        ],
    ],

];
