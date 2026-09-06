<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Social sign-in
|--------------------------------------------------------------------------
|
| The providers the app may offer. A provider listed here is *offered* only
| once its credentials exist in config/services.php, so adding a button is an
| .env change and nothing else. App\Support\SocialProviders is the only
| reader; see docs/social-login.md for the wiring.
|
| `icon` is an Iconify name, resolved by the client's Icon component.
|
*/

return [

    'providers' => [

        'google' => [
            'label' => 'Google',
            'icon' => 'logos:google-icon',
            'scopes' => ['openid', 'profile', 'email'],
        ],

        'github' => [
            'label' => 'GitHub',
            'icon' => 'simple-icons:github',
            'scopes' => ['read:user', 'user:email'],
        ],

        'gitlab' => [
            'label' => 'GitLab',
            'icon' => 'logos:gitlab',
            'scopes' => ['read_user'],
        ],

        'bitbucket' => [
            'label' => 'Bitbucket',
            'icon' => 'logos:bitbucket',
            'scopes' => ['account', 'email'],
        ],

        'linkedin-openid' => [
            'label' => 'LinkedIn',
            'icon' => 'logos:linkedin-icon',
            'scopes' => ['openid', 'profile', 'email'],
        ],

    ],

];
