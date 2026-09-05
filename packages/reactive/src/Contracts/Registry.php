<?php

declare(strict_types=1);

namespace Kit\Reactive\Contracts;

use Kit\Reactive\Registry\Subscription;

/**
 * The subscription registry (§4.4). Redis in every real environment; an
 * in-memory implementation carries the sqlite test suite.
 */
interface Registry
{
    public function put(Subscription $subscription): void;

    public function get(string $id): ?Subscription;

    public function delete(string $id): void;

    /** Refresh the TTL without changing anything else. */
    public function touch(string $id): void;

    public function updateResult(string $id, string $hash, int $mutationId): void;

    /**
     * Replace the dependency sets with the subscription's current tables and deps.
     */
    public function syncDeps(Subscription $subscription): void;

    /** Delete every subscription of a user (logout). */
    public function purgeUser(string $userId): void;

    public function countForUser(string $userId): int;

    /**
     * @return array<int, string>
     */
    public function idsForUser(string $userId): array;

    /**
     * @return array<int, string>
     */
    public function idsForTable(string $table): array;

    /**
     * `column:value` keys that have at least one subscriber on this table.
     *
     * @return array<int, string>
     */
    public function predicateKeys(string $table): array;

    /**
     * @return array<int, string>
     */
    public function idsForPredicate(string $table, string $column, string $value): array;

    public function nextMutationId(): int;

    public function currentMutationId(): int;

    /** SET NX with a millisecond TTL; true when this caller acquired it. */
    public function debounce(string $id, int $milliseconds): bool;

    public function releaseDebounce(string $id): void;

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withLock(string $id, callable $callback): mixed;

    /** Drop dep-set members whose subscription hash has expired. Returns the count removed. */
    public function gc(): int;

    public function incrementMetric(string $name, int $by = 1): void;

    public function recordLatency(float $milliseconds): void;

    /**
     * @return array{subscriptions: int, users: int, metrics: array<string, int>, latencies: array<int, float>}
     */
    public function stats(): array;

    /** Wipe everything; tests and `reactive:gc --flush`. */
    public function flush(): void;
}
