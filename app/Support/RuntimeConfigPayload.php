<?php

declare(strict_types=1);

namespace App\Support;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The window.__APP_CONFIG__ payload injected into the SPA shell. Its
 * TypeScript type is generated from this class by types:generate, so the two
 * cannot drift.
 */
#[TypeScript]
class RuntimeConfigPayload extends Data
{
    /**
     * @param  array{realtime: bool, registration: bool, impersonation: bool, ai: bool}  $features
     */
    public function __construct(
        public string $version,
        public string $apiBaseUrl,
        public string $locale,
        public array $features,
        public ?EchoConfigPayload $echo,
        public ?AnalyticsPayload $analytics,
    ) {}

    public static function build(): self
    {
        return new self(
            version: (string) config('app.version'),
            apiBaseUrl: (string) config('app.api_url', ''),
            locale: app()->getLocale(),
            features: FeatureGate::features(),
            echo: self::echo(),
            analytics: self::analytics(),
        );
    }

    /**
     * Null when realtime is not configured, so the SPA skips Echo entirely
     * instead of retry-looping against a websocket that is not there.
     */
    private static function echo(): ?EchoConfigPayload
    {
        if (! FeatureGate::realtimeEnabled()) {
            return null;
        }

        $port = (int) config('reverb.apps.apps.0.options.port');

        return new EchoConfigPayload(
            key: (string) config('reverb.apps.apps.0.key'),
            wsHost: (string) config('reverb.apps.apps.0.options.host'),
            wsPort: $port,
            wssPort: $port,
            forceTLS: config('reverb.apps.apps.0.options.scheme') === 'https',
        );
    }

    private static function analytics(): ?AnalyticsPayload
    {
        $key = config('services.analytics.key');

        if (! is_string($key) || $key === '') {
            return null;
        }

        return new AnalyticsPayload(key: $key, host: (string) config('services.analytics.host', ''));
    }
}
