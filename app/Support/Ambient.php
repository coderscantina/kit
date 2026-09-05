<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Ambient container bindings that live for one request or one job.
 *
 * Under Octane the container survives between requests, so a bare
 * app()->instance() set in middleware is still there for the next request
 * unless config/octane.php flushes it. Every ambient key is declared in
 * config('kit.ambient_bindings'); enter() sets it for the duration of a
 * callback and restores whatever was there before, QueuedJob snapshots the
 * whole set around execute().
 */
final class Ambient
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function enter(string $key, mixed $value, callable $callback): mixed
    {
        $had = app()->bound($key);
        $prior = $had ? app($key) : null;

        app()->instance($key, $value);

        try {
            return $callback();
        } finally {
            if ($had) {
                app()->instance($key, $prior);
            } else {
                app()->offsetUnset($key);
            }
        }
    }

    /**
     * @return array<string, array{bound: bool, value: mixed}>
     */
    public static function snapshot(): array
    {
        $snapshot = [];

        foreach (self::keys() as $key) {
            $bound = app()->bound($key);
            $snapshot[$key] = ['bound' => $bound, 'value' => $bound ? app($key) : null];
        }

        return $snapshot;
    }

    /**
     * @param  array<string, array{bound: bool, value: mixed}>  $snapshot
     */
    public static function restore(array $snapshot): void
    {
        foreach ($snapshot as $key => $entry) {
            if ($entry['bound']) {
                app()->instance($key, $entry['value']);
            } else {
                app()->offsetUnset($key);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        /** @var array<int, string> $keys */
        $keys = config('kit.ambient_bindings', []);

        return $keys;
    }
}
