<?php

declare(strict_types=1);
use App\Models\User;

return [

    // redis in every real environment; array carries the sqlite test suite.
    'registry' => env('REACTIVE_REGISTRY', 'redis'),

    'redis_connection' => env('REACTIVE_REDIS_CONNECTION', 'default'),

    // Dedicated queue with its own Horizon supervisor, so a slow default
    // queue never delays a push.
    'queue' => 'reactive',

    // Backstop for subscriptions whose channel-removed event never arrived.
    'ttl_seconds' => 3600,

    // Bursts within this window coalesce into one recompute.
    'debounce_ms' => 50,

    // Reverb drops frames over 10 KB; results above this ride as hash-only
    // pushes and the client fetches through /rq/query.
    'inline_result_bytes' => 8192,

    'max_subscriptions_per_user' => 200,

    'max_args_bytes' => 16384,

    'rate_limits' => [
        'default' => 120,
        // The large-result fallback and prefetches use /rq/query.
        'query' => 600,
    ],

    // The authenticated group the /rq/* routes sit in. Keep the fail-closed
    // app.access gate here.
    'middleware' => ['web', 'auth:sanctum', 'app.access'],

    'user_model' => User::class,

    // namespace prefix => directory scanned for Queries/ and Mutations/.
    'discovery' => [
        'App\\Features\\' => app_path('Features'),
    ],

    // Classes registered outside discovery (tests).
    'classes' => [],

];
