<?php

declare(strict_types=1);

namespace Kit\Reactive\Contracts;

use Kit\Reactive\Registry\Computation;
use Kit\Reactive\Registry\Subscription;

/**
 * The subscription registry (§4.4). Redis in every real environment; an
 * in-memory implementation carries the sqlite test suite.
 *
 * Two levels. A {@see Computation} is one query with one set of args and its
 * last result; the dependency sets, the debounce and the recompute lock are
 * all keyed on it. A {@see Subscription} is one client watching one
 * computation, and is what channel auth, revocation and cleanup act on.
 */
interface Registry
{
    public function putComputation(Computation $computation): void;

    public function computation(string $key): ?Computation;

    /** Store a new result and watermark, and refresh the TTL. */
    public function updateComputation(string $key, string $hash, string $result, int $mutationId): void;

    /** The result did not change: move the watermark and refresh the TTL only. */
    public function touchComputation(string $key, int $mutationId): void;

    /**
     * Replace the dependency sets with the computation's current tables and deps.
     */
    public function syncDeps(Computation $computation): void;

    /** Drop a computation and its dependency entries; the last subscriber left. */
    public function forgetComputation(string $key): void;

    /**
     * Subscription ids watching a computation.
     *
     * @return array<int, string>
     */
    public function subscribersOf(string $key): array;

    public function put(Subscription $subscription): void;

    public function get(string $id): ?Subscription;

    /** Also drops the computation when this was its last subscriber. */
    public function delete(string $id): void;

    /** Refresh the TTL without changing anything else. */
    public function touch(string $id): void;

    /** Delete every subscription of a user (logout, role change). */
    public function purgeUser(string $userId): void;

    public function countForUser(string $userId): int;

    /**
     * @return array<int, string>
     */
    public function idsForUser(string $userId): array;

    /**
     * Computation keys depending on a table.
     *
     * @return array<int, string>
     */
    public function keysForTable(string $table): array;

    /**
     * `column:value` keys that have at least one computation on this table.
     *
     * @return array<int, string>
     */
    public function predicateKeys(string $table): array;

    /**
     * @return array<int, string>
     */
    public function keysForPredicate(string $table, string $column, string $value): array;

    public function nextMutationId(): int;

    public function currentMutationId(): int;

    /** SET NX with a millisecond TTL; true when this caller acquired it. */
    public function debounce(string $key, int $milliseconds): bool;

    public function releaseDebounce(string $key): void;

    /**
     * Run the callback under an exclusive lock on the key.
     *
     * Returns whatever the callback returned, or null when the lock could not
     * be taken before the deadline. A caller that cannot tell those two apart
     * must not run work that needs the lock: null means "somebody else has it,
     * skip this round".
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|null
     */
    public function withLock(string $key, callable $callback): mixed;

    /**
     * Drop dep-set members whose computation has expired, subscriptions whose
     * computation is gone, and computations nobody watches. Returns the count.
     */
    public function gc(): int;

    /**
     * Registry sizes. Counts come from the index sets, so they are
     * approximate between garbage collections: a member whose key has
     * expired still counts until `gc()` drops it.
     *
     * @return array{subscriptions: int, computations: int, users: int}
     */
    public function stats(): array;

    /** Wipe everything; tests and `reactive:gc --flush`. */
    public function flush(): void;
}
