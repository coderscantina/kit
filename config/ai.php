<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Provider
    |--------------------------------------------------------------------------
    |
    | OpenRouter only, on purpose: it already fronts every major model behind
    | one OpenAI-compatible API, so a second driver would buy nothing but a
    | second thing to keep working. Everything below the AiDriver contract is
    | swappable when that changes.
    |
    */

    'driver' => env('AI_DRIVER', 'openrouter'),

    'drivers' => [

        'openrouter' => [
            'api_key' => env('OPENROUTER_API_KEY'),
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),

            // OpenRouter ranks and attributes traffic by these two headers.
            'site_url' => env('OPENROUTER_SITE_URL'),
            'site_name' => env('OPENROUTER_SITE_NAME'),

            // Seconds. The read timeout applies between chunks, not to the
            // whole stream: a model that thinks for two minutes before its
            // first token is normal, a socket that goes quiet is not.
            'connect_timeout' => (int) env('AI_CONNECT_TIMEOUT', 10),
            'stream_idle_timeout' => (int) env('AI_STREAM_IDLE_TIMEOUT', 120),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Model
    |--------------------------------------------------------------------------
    |
    | An action may name its own model; this is what it falls back to. Check a
    | slug against the live catalogue with `php artisan ai:models --search=`.
    |
    */

    'model' => env('AI_MODEL', 'anthropic/claude-sonnet-4.5'),

    'defaults' => [
        'max_tokens' => (int) env('AI_MAX_TOKENS', 2048),
        // Low by default: most actions want a dependable shape rather than
        // variety. An action raises it in options() when it wants prose.
        'temperature' => (float) env('AI_TEMPERATURE', 0.3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */

    'limits' => [
        // How many times a stream may answer a tool call and come back for
        // more. Each round is another billed request, so it is capped.
        'max_tool_depth' => (int) env('AI_MAX_TOOL_DEPTH', 4),

        // Requests per minute per user against /api/ai/stream.
        'rate_limit' => (int) env('AI_RATE_LIMIT', 20),
    ],

    // The model catalogue changes daily at most, and a cold fetch costs a
    // round trip on the first stream of the day.
    'models_cache_ttl' => (int) env('AI_MODELS_CACHE_TTL', 43200),

    /*
    |--------------------------------------------------------------------------
    | Action discovery
    |--------------------------------------------------------------------------
    |
    | namespace prefix => directory scanned for #[AiStream] classes. Features
    | own their actions; app/Ai holds the ones that belong to no feature.
    |
    */

    'discovery' => [
        'App\\Features\\' => app_path('Features'),
        'App\\Ai\\' => app_path('Ai'),
    ],

    // Classes registered outside discovery (tests).
    'classes' => [],

];
