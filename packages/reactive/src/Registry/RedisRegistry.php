<?php

declare(strict_types=1);

namespace Kit\Reactive\Registry;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Kit\Reactive\Contracts\Registry;
use Kit\Reactive\Dep;

/**
 * Redis-backed registry, keys as in §4.4:
 *
 *   rq:comp:{key}             hash, the query, args, result and watermark
 *   rq:comp:{key}:subs        set of subscription ids watching it
 *   rq:sub:{id}               hash, the computation key and the user
 *   rq:dep:t:{table}          set of computation keys with table-level tracking
 *   rq:dep:p:{table}:{c}:{v}  set of computation keys with that predicate
 *   rq:dep:pk:{table}         set of "column:value" keys present for the table
 *   rq:user:{user_id}         set of the user's subscription ids
 *   rq:debounce:{key}         NX marker, 50 ms, per computation
 *   reactive:mutation_id      global monotonic counter, no TTL
 *
 * Everything but the counter carries the same TTL, refreshed together on
 * every recompute, so a computation and its subscriptions expire as a unit.
 *
 * Plus four index sets (rq:index:comps, :subs, :tables, :users) so gc and
 * stats never need SCAN. Commands use the variadic forms that both predis
 * and phpredis accept.
 */
final class RedisRegistry implements Registry
{
    private const string SET_NX_PX = "return redis.call('set', KEYS[1], ARGV[1], 'PX', ARGV[2], 'NX') and 1 or 0";

    /** Delete only when the value still matches, so nobody frees another owner's lock. */
    private const string DEL_IF_MATCH = "if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) else return 0 end";

    public function __construct(
        private readonly RedisFactory $redis,
        private readonly string $connection,
        private readonly int $ttlSeconds,
        private readonly int $lockWaitMs = 5000,
    ) {}

    public function putComputation(Computation $computation): void
    {
        $redis = $this->redis();
        $redis->hmset($this->compKey($computation->key), $computation->toHash());
        $redis->expire($this->compKey($computation->key), $this->ttlSeconds);
        $redis->sadd('rq:index:comps', $computation->key);
        $this->addDeps($computation);
    }

    public function computation(string $key): ?Computation
    {
        /** @var array<string, string> $hash */
        $hash = $this->redis()->hgetall($this->compKey($key));

        if ($hash === [] || ! isset($hash['query'])) {
            return null;
        }

        return Computation::fromHash($key, $hash);
    }

    public function computations(): array
    {
        $computations = [];

        foreach ($this->members('rq:index:comps') as $key) {
            $computation = $this->computation($key);

            if ($computation !== null) {
                $computations[] = $computation;
            }
        }

        return $computations;
    }

    public function updateComputation(string $key, string $hash, string $result, int $mutationId, ?LastRecompute $recompute = null): void
    {
        $this->redis()->hmset($this->compKey($key), [
            ...($recompute?->toHash() ?? []),
            'result_hash' => $hash,
            'result' => $result,
            'last_mutation_id' => (string) $mutationId,
        ]);

        $this->refresh($key);
    }

    public function touchComputation(string $key, int $mutationId, ?LastRecompute $recompute = null): void
    {
        $this->redis()->hmset($this->compKey($key), [
            ...($recompute?->toHash() ?? []),
            'last_mutation_id' => (string) $mutationId,
        ]);
        $this->refresh($key);
    }

    public function syncDeps(Computation $computation): void
    {
        $stored = $this->computation($computation->key);

        if ($stored !== null) {
            $this->removeDeps($stored);
        }

        $this->redis()->hmset($this->compKey($computation->key), [
            'tables' => json_encode($computation->tables, JSON_THROW_ON_ERROR),
            'deps' => json_encode(array_map(fn (Dep $dep) => $dep->toArray(), $computation->deps), JSON_THROW_ON_ERROR),
        ]);

        $this->addDeps($computation);
    }

    public function forgetComputation(string $key): void
    {
        $computation = $this->computation($key);

        if ($computation !== null) {
            $this->removeDeps($computation);
        }

        // The lock is deliberately not deleted here: a recompute may hold it
        // while its last subscriber goes away, and only its owner releases it.
        $this->redis()->del($this->compKey($key), "rq:comp:{$key}:subs", "rq:debounce:{$key}");
        $this->redis()->srem('rq:index:comps', $key);
    }

    public function subscribersOf(string $key): array
    {
        return $this->members("rq:comp:{$key}:subs");
    }

    public function put(Subscription $subscription): void
    {
        $redis = $this->redis();
        $redis->hmset($this->subKey($subscription->id), $subscription->toHash());
        $redis->expire($this->subKey($subscription->id), $this->ttlSeconds);
        $redis->sadd("rq:comp:{$subscription->computationKey}:subs", $subscription->id);
        $redis->expire("rq:comp:{$subscription->computationKey}:subs", $this->ttlSeconds);
        $redis->sadd("rq:user:{$subscription->userId}", $subscription->id);
        $redis->sadd('rq:index:subs', $subscription->id);
        $redis->sadd('rq:index:users', $subscription->userId);
    }

    public function get(string $id): ?Subscription
    {
        /** @var array<string, string> $hash */
        $hash = $this->redis()->hgetall($this->subKey($id));

        if ($hash === [] || ! isset($hash['computation'])) {
            return null;
        }

        return Subscription::fromHash($id, $hash);
    }

    public function delete(string $id): void
    {
        $subscription = $this->get($id);

        if ($subscription === null) {
            $this->redis()->srem('rq:index:subs', $id);

            return;
        }

        $watchers = "rq:comp:{$subscription->computationKey}:subs";

        $this->redis()->srem($watchers, $id);
        $this->redis()->srem("rq:user:{$subscription->userId}", $id);
        $this->redis()->srem('rq:index:subs', $id);
        $this->redis()->del($this->subKey($id));

        if ((int) $this->redis()->scard($watchers) === 0) {
            $this->forgetComputation($subscription->computationKey);
        }

        // stats() counts the index sets, so drop a user that has nothing left
        // rather than leaving the count to drift until the next gc.
        if ((int) $this->redis()->scard("rq:user:{$subscription->userId}") === 0) {
            $this->redis()->srem('rq:index:users', $subscription->userId);
        }
    }

    public function touch(string $id): void
    {
        $this->redis()->expire($this->subKey($id), $this->ttlSeconds);
    }

    public function purgeUser(string $userId): void
    {
        foreach ($this->idsForUser($userId) as $id) {
            $this->delete($id);
        }

        $this->redis()->del("rq:user:{$userId}");
        $this->redis()->srem('rq:index:users', $userId);
    }

    public function countForUser(string $userId): int
    {
        return (int) $this->redis()->scard("rq:user:{$userId}");
    }

    public function idsForUser(string $userId): array
    {
        return $this->members("rq:user:{$userId}");
    }

    public function keysForTable(string $table): array
    {
        return $this->members("rq:dep:t:{$table}");
    }

    public function predicateKeys(string $table): array
    {
        return $this->members("rq:dep:pk:{$table}");
    }

    public function keysForPredicate(string $table, string $column, string $value): array
    {
        return $this->members($this->predicateKey($table, $column, $value));
    }

    public function nextMutationId(): int
    {
        return (int) $this->redis()->incr('reactive:mutation_id');
    }

    public function currentMutationId(): int
    {
        return (int) $this->redis()->get('reactive:mutation_id');
    }

    public function debounce(string $key, int $milliseconds): bool
    {
        return $this->setNx("rq:debounce:{$key}", $milliseconds);
    }

    public function releaseDebounce(string $key): void
    {
        $this->redis()->del("rq:debounce:{$key}");
    }

    public function withLock(string $key, callable $callback, ?int $waitMs = null): mixed
    {
        // A short exclusive lock around the compare-and-store, so two
        // recomputes of the same computation cannot interleave their hash
        // writes and pushes.
        //
        // The value is a token unique to this caller. Running the callback
        // without the lock would defeat the point, and deleting the key
        // unconditionally would release a lock another worker owns, so a
        // caller that loses the race gets null and does nothing.
        $lock = "rq:lock:{$key}";
        $token = bin2hex(random_bytes(16));
        $deadline = microtime(true) + ($waitMs ?? $this->lockWaitMs) / 1000;

        // The lock outlives the wait, so a worker that gave up cannot come
        // back to find the holder's lock already expired under it.
        while (! $this->setNx($lock, $this->lockWaitMs, $token)) {
            if (microtime(true) >= $deadline) {
                return null;
            }

            usleep(5_000);
        }

        try {
            return $callback();
        } finally {
            $this->releaseLock($lock, $token);
        }
    }

    public function gc(): int
    {
        $removed = 0;
        $alive = fn (string $key): bool => (bool) $this->redis()->exists($this->compKey($key));

        foreach ($this->members('rq:index:tables') as $table) {
            $keys = ["rq:dep:t:{$table}"];
            foreach ($this->predicateKeys($table) as $predicate) {
                [$column, $value] = explode(':', $predicate, 2) + [1 => ''];
                $keys[] = $this->predicateKey($table, $column, $value);
            }

            foreach ($keys as $key) {
                foreach ($this->members($key) as $computationKey) {
                    if (! $alive($computationKey)) {
                        $this->redis()->srem($key, $computationKey);
                        $removed++;
                    }
                }
            }
        }

        // A subscription whose computation expired can never be pushed again.
        foreach ($this->members('rq:index:subs') as $id) {
            $subscription = $this->get($id);

            if ($subscription === null) {
                $this->redis()->srem('rq:index:subs', $id);

                continue;
            }

            if (! $alive($subscription->computationKey)) {
                $this->delete($id);
                $removed++;
            }
        }

        foreach ($this->members('rq:index:users') as $userId) {
            foreach ($this->idsForUser($userId) as $id) {
                if ($this->get($id) === null) {
                    $this->redis()->srem("rq:user:{$userId}", $id);
                    $removed++;
                }
            }

            // stats() counts this set, so a user with nothing left must leave it.
            if ((int) $this->redis()->scard("rq:user:{$userId}") === 0) {
                $this->redis()->srem('rq:index:users', $userId);
            }
        }

        // A computation nobody watches recomputes for nothing.
        foreach ($this->members('rq:index:comps') as $key) {
            if (! $alive($key)) {
                $this->redis()->srem('rq:index:comps', $key);

                continue;
            }

            if ($this->subscribersOf($key) === []) {
                $this->forgetComputation($key);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Three SCARDs. The old version ran one EXISTS per index member, which
     * made /rq/health cost O(subscriptions) Redis round trips. The index
     * sets can hold members whose key has since expired, so these counts are
     * an upper bound until `gc()` prunes them.
     */
    public function stats(): array
    {
        return [
            'subscriptions' => (int) $this->redis()->scard('rq:index:subs'),
            'computations' => (int) $this->redis()->scard('rq:index:comps'),
            'users' => (int) $this->redis()->scard('rq:index:users'),
        ];
    }

    public function flush(): void
    {
        foreach ($this->members('rq:index:subs') as $id) {
            $this->redis()->del($this->subKey($id));
        }

        foreach ($this->members('rq:index:comps') as $key) {
            $this->redis()->del($this->compKey($key), "rq:comp:{$key}:subs", "rq:debounce:{$key}", "rq:lock:{$key}");
        }

        foreach ($this->members('rq:index:tables') as $table) {
            foreach ($this->predicateKeys($table) as $predicate) {
                [$column, $value] = explode(':', $predicate, 2) + [1 => ''];
                $this->redis()->del($this->predicateKey($table, $column, $value));
            }
            $this->redis()->del("rq:dep:t:{$table}", "rq:dep:pk:{$table}");
        }

        foreach ($this->members('rq:index:users') as $userId) {
            $this->redis()->del("rq:user:{$userId}");
        }

        $this->redis()->del('rq:index:comps', 'rq:index:subs', 'rq:index:tables', 'rq:index:users', 'reactive:mutation_id');
    }

    /**
     * A computation and the subscriptions watching it expire together, so
     * every write to the computation pushes all of their TTLs out.
     */
    private function refresh(string $key): void
    {
        $this->redis()->expire($this->compKey($key), $this->ttlSeconds);
        $this->redis()->expire("rq:comp:{$key}:subs", $this->ttlSeconds);

        foreach ($this->subscribersOf($key) as $id) {
            $this->touch($id);
        }
    }

    private function addDeps(Computation $computation): void
    {
        foreach ($computation->tableLevelTables() as $table) {
            $this->redis()->sadd("rq:dep:t:{$table}", $computation->key);
            $this->redis()->sadd('rq:index:tables', $table);
        }

        foreach ($computation->predicateDeps() as $dep) {
            $this->redis()->sadd($this->predicateKey($dep->table, (string) $dep->column, (string) $dep->value), $computation->key);
            $this->redis()->sadd("rq:dep:pk:{$dep->table}", "{$dep->column}:{$dep->value}");
            $this->redis()->sadd('rq:index:tables', $dep->table);
        }
    }

    private function removeDeps(Computation $computation): void
    {
        foreach ($computation->tableLevelTables() as $table) {
            $this->redis()->srem("rq:dep:t:{$table}", $computation->key);
        }

        foreach ($computation->predicateDeps() as $dep) {
            $key = $this->predicateKey($dep->table, (string) $dep->column, (string) $dep->value);
            $this->redis()->srem($key, $computation->key);

            if ((int) $this->redis()->scard($key) === 0) {
                $this->redis()->srem("rq:dep:pk:{$dep->table}", "{$dep->column}:{$dep->value}");
            }
        }
    }

    private function setNx(string $key, int $milliseconds, string $value = '1'): bool
    {
        return (int) $this->eval(self::SET_NX_PX, $key, $value, (string) $milliseconds) === 1;
    }

    private function releaseLock(string $key, string $token): void
    {
        $this->eval(self::DEL_IF_MATCH, $key, $token);
    }

    /**
     * phpredis and predis take EVAL arguments in a different order; the
     * Laravel wrapper normalises phpredis, predis speaks Redis natively.
     */
    private function eval(string $script, string $key, string ...$arguments): mixed
    {
        $connection = $this->redis();
        $call = [$script, 1, $key, ...$arguments];

        return $connection instanceof PhpRedisConnection
            ? $connection->eval(...$call)
            : $connection->command('eval', $call);
    }

    /**
     * @return array<int, string>
     */
    private function members(string $key): array
    {
        /** @var array<int, string> $members */
        $members = $this->redis()->smembers($key) ?: [];

        return array_values($members);
    }

    private function compKey(string $key): string
    {
        return "rq:comp:{$key}";
    }

    private function subKey(string $id): string
    {
        return "rq:sub:{$id}";
    }

    private function predicateKey(string $table, string $column, string $value): string
    {
        return "rq:dep:p:{$table}:{$column}:{$value}";
    }

    private function redis(): Connection
    {
        return $this->redis->connection($this->connection);
    }
}
