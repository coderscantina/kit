<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The only reader of config/social.php.
 *
 * A provider is *listed* by config/social.php and *offered* by whether its
 * credentials exist in config/services.php. Splitting the two is what makes a
 * derived app's sign-in page an .env change: no button appears for a provider
 * nobody has registered an OAuth client for.
 */
final class SocialProviders
{
    /**
     * The providers this installation can actually sign a user in with.
     *
     * @return array<string, array{label: string, icon: string, scopes: list<string>}>
     */
    public static function enabled(): array
    {
        /** @var array<string, array<string, mixed>> $configured */
        $configured = config('social.providers', []);

        $enabled = [];

        foreach ($configured as $key => $provider) {
            if (! self::hasCredentials($key)) {
                continue;
            }

            $enabled[$key] = [
                'label' => (string) ($provider['label'] ?? ucfirst($key)),
                'icon' => (string) ($provider['icon'] ?? 'lucide:key-round'),
                'scopes' => array_values(array_map(strval(...), (array) ($provider['scopes'] ?? []))),
            ];
        }

        return $enabled;
    }

    public static function supports(string $provider): bool
    {
        return array_key_exists($provider, self::enabled());
    }

    /**
     * @return list<string>
     */
    public static function scopes(string $provider): array
    {
        return self::enabled()[$provider]['scopes'] ?? [];
    }

    public static function anyEnabled(): bool
    {
        return self::enabled() !== [];
    }

    private static function hasCredentials(string $provider): bool
    {
        return filled(config("services.{$provider}.client_id"))
            && filled(config("services.{$provider}.client_secret"));
    }
}
