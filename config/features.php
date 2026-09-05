<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Feature switches
|--------------------------------------------------------------------------
|
| null means "derive": realtime from whether Reverb is configured, registration
| from the first-account latch. An explicit boolean overrides. FeatureGate is
| the only reader; nothing else should touch these values.
|
*/

return [

    'realtime' => env('APP_FEATURE_REALTIME'),

    'registration' => env('APP_ALLOW_REGISTRATION'),

    'impersonation' => env('APP_FEATURE_IMPERSONATION', true),

];
