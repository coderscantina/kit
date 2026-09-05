<?php

declare(strict_types=1);

namespace Kit\Reactive\Runtime;

/**
 * Turns whatever handle() returned into plain JSON data, and hashes it in a
 * key-order-independent way so a reordered associative array is not a
 * "change".
 */
final class Canonical
{
    /**
     * A full JSON round-trip: Data objects, collections, paginators and
     * models all serialize the way the client would receive them, and
     * nothing live (a model, a closure) survives into the registry.
     */
    public static function normalize(mixed $value): mixed
    {
        return json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function hash(mixed $normalized): string
    {
        return hash('xxh3', self::encode($normalized));
    }

    public static function encode(mixed $normalized): string
    {
        return json_encode(self::sortKeys($normalized), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $sorted = array_map(fn (mixed $item) => self::sortKeys($item), $value);

        if (! array_is_list($sorted)) {
            ksort($sorted);
        }

        return $sorted;
    }
}
