<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Feature switches
|--------------------------------------------------------------------------
|
| null means "derive": realtime from whether Reverb is configured, registration
| from the first-account latch, ai from whether the provider has a key, push
| from whether a VAPID key pair exists, sms from whether a sender is
| configured, social from whether any provider has credentials. An explicit
| boolean overrides. FeatureGate is the only reader;
| nothing else should touch these values.
|
*/

return [

    'realtime' => env('APP_FEATURE_REALTIME'),

    'registration' => env('APP_ALLOW_REGISTRATION'),

    'impersonation' => env('APP_FEATURE_IMPERSONATION', true),

    'ai' => env('APP_FEATURE_AI'),

    'push' => env('APP_FEATURE_PUSH'),

    'sms' => env('APP_FEATURE_SMS'),

    'social' => env('APP_FEATURE_SOCIAL'),

];
