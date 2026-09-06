<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A readable label for a session row. Deliberately coarse: the point is to let
 * someone recognise their own devices in a list, not to fingerprint them.
 */
final class UserAgent
{
    /** @var list<array{0: string, 1: string}> Ordered: the first match wins. */
    private const BROWSERS = [
        ['Edg/', 'Edge'],
        ['OPR/', 'Opera'],
        ['Firefox/', 'Firefox'],
        ['Chrome/', 'Chrome'],
        ['Safari/', 'Safari'],
    ];

    /** @var list<array{0: string, 1: string}> */
    private const PLATFORMS = [
        ['iPhone', 'iPhone'],
        ['iPad', 'iPad'],
        ['Android', 'Android'],
        ['Macintosh', 'macOS'],
        ['Windows', 'Windows'],
        ['Linux', 'Linux'],
    ];

    public static function describe(?string $userAgent): string
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return __('auth.unknown_device');
        }

        $browser = self::match(self::BROWSERS, $userAgent);
        $platform = self::match(self::PLATFORMS, $userAgent);

        return match (true) {
            $browser !== null && $platform !== null => "{$browser} on {$platform}",
            $browser !== null => $browser,
            $platform !== null => $platform,
            default => __('auth.unknown_device'),
        };
    }

    /**
     * @param  list<array{0: string, 1: string}>  $candidates
     */
    private static function match(array $candidates, string $userAgent): ?string
    {
        foreach ($candidates as [$needle, $label]) {
            if (str_contains($userAgent, $needle)) {
                return $label;
            }
        }

        return null;
    }
}
