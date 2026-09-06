<?php

declare(strict_types=1);

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

    // A write that wakes at most this many computations recomputes and
    // pushes them inside its own request, before the response returns; more
    // than that goes to the queue as one Invalidate job. 0 sends everything
    // to the queue.
    'inline_recomputes' => 4,

    // How long an inline recompute waits for the per-computation lock before
    // handing the key to the queue. Short: it is inside a request.
    'inline_lock_wait_ms' => 200,

    // How long a recompute waits for the per-computation lock before it
    // gives up and lets the worker that holds it do the push.
    'lock_wait_ms' => 5000,

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

    // A class-string, not User::class: the config is cached and the package
    // must not depend on the app's namespace.
    'user_model' => env('REACTIVE_USER_MODEL', 'App\\Models\\User'),

    // Where `reactive:cache` writes the name maps; `optimize` runs it.
    'cache_path' => base_path('bootstrap/cache/reactive.php'),

    // namespace prefix => directory scanned for #[ReactiveQuery] and
    // #[ReactiveMutation] classes.
    'discovery' => [
        'App\\Queries\\' => app_path('Queries'),
        'App\\Mutations\\' => app_path('Mutations'),
    ],

    // Classes registered outside discovery (tests).
    'classes' => [],

];
