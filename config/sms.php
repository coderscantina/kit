<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SMS
|--------------------------------------------------------------------------
|
| The kit ships the seam and a `log` driver, not a provider account. `log`
| writes the message to the application log, which is enough to develop the
| phone verification flow and the SMS channel end to end without a bill.
|
| A real deployment binds its own sender: implement App\Services\Sms\SmsSender
| and register it under a driver key here. FeatureGate::smsEnabled() derives
| from `driver` being something other than null, so an installation with no
| sender simply has no SMS channel rather than a switch that cannot work.
|
*/

return [

    'driver' => env('SMS_DRIVER', 'log'),

    'from' => env('SMS_FROM'),

    'drivers' => [
        'log' => [
            'channel' => env('SMS_LOG_CHANNEL'),
        ],
    ],

    /*
    | Phone verification: how long a code is good for, how many guesses it
    | takes, and how long before another one can be asked for.
    */
    'verification' => [
        'code_length' => 6,
        'ttl_minutes' => (int) env('SMS_VERIFICATION_TTL_MINUTES', 10),
        'max_attempts' => 5,
        'resend_seconds' => 60,
    ],

];
