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
 *   rq:sub:{id}               hash, TTL 1 h refreshed on every recompute
 *   rq:dep:t:{table}          set of subscription ids with table-level tracking
 *   rq:dep:p:{table}:{c}:{v}  set of subscription ids with that predicate
 *   rq:dep:pk:{table}         set of "column:value" keys present for the table
 *   rq:user:{user_id}         set of the user's subscription ids
 *   rq:debounce:{id}          NX marker, 50 ms
 *   reactive:mutation_id      global monotonic counter, no TTL
 *
 * Plus three index sets (rq:index:subs, rq:index:tables, rq:index:users) so
 * gc and stats never need SCAN. Commands use the variadic forms that both
 * predis and phpredis accept.
 */
final class RedisRegistry implements Registry
{
    private const int LATENCY_SAMPLES = 200;

    private const string SET_NX_PX = "return redis.call('set', KEYS[1], ARGV[1], 'PX', ARGV[2], 'NX') and 1 or 0";

    public function __construct(
        private readonly RedisFactory $redis,
        private readonly string $connection,
        private readonly int $ttlSeconds,
    ) {}

    public function put(Subscription $subscription): void
    {
        $redis = $this->redis();
        $redis->hmset($this->subKey($subscription->id), $subscription->toHash());
        $redis->expire($this->subKey($subscription->id), $this->ttlSeconds);
        $redis->sadd("rq:user:{$subscription->userId}", $subscription->id);
        $redis->sadd('rq:index:subs', $subscription->id);
        $redis->sadd('rq:index:users', $subscription->userId);
        $this->addDeps($subscription);
    }

    public function get(string $id): ?Subscription
    {
        /** @var array<string, string> $hash */
        $hash = $this->redis()->hgetall($this->subKey($id));

        if ($hash === [] || ! isset($hash['query'])) {
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

        $this->removeDeps($subscription);
        $this->redis()->srem("rq:user:{$subscription->userId}", $id);
        $this->redis()->srem('rq:index:subs', $id);
        $this->redis()->del($this->subKey($id), "rq:debounce:{$id}");
    }

    public function touch(string $id): void
    {
        $this->redis()->expire($this->subKey($id), $this->ttlSeconds);
    }

    public function updateResult(string $id, string $hash, int $mutationId): void
    {
        $this->redis()->hmset($this->subKey($id), ['result_hash' => $hash, 'last_mutation_id' => (string) $mutationId]);
        $this->touch($id);
    }

    public function syncDeps(Subscription $subscription): void
    {
        $stored = $this->get($subscription->id);

        if ($stored !== null) {
            $this->removeDeps($stored);
        }

        $this->redis()->hmset($this->subKey($subscription->id), [
            'tables' => json_encode($subscription->tables, JSON_THROW_ON_ERROR),
            'deps' => json_encode(array_map(fn (Dep $dep) => $dep->toArray(), $subscription->deps), JSON_THROW_ON_ERROR),
        ]);

        $this->addDeps($subscription);
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

    public function idsForTable(string $table): array
    {
        return $this->members("rq:dep:t:{$table}");
    }

    public function predicateKeys(string $table): array
    {
        return $this->members("rq:dep:pk:{$table}");
    }

    public function idsForPredicate(string $table, string $column, string $value): array
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

    public function debounce(string $id, int $milliseconds): bool
    {
        return $this->setNx("rq:debounce:{$id}", $milliseconds);
    }

    public function releaseDebounce(string $id): void
    {
        $this->redis()->del("rq:debounce:{$id}");
    }

    public function withLock(string $id, callable $callback): mixed
    {
        // A short exclusive lock around the compare-and-store in the worker,
        // so two recomputes of the same subscription cannot interleave their
        // hash writes and pushes.
        $key = "rq:lock:{$id}";
        $deadline = microtime(true) + 5;

        while (! $this->setNx($key, 5000)) {
            if (microtime(true) > $deadline) {
                break;
            }
            usleep(5_000);
        }

        try {
            return $callback();
        } finally {
            $this->redis()->del($key);
        }
    }

    public function gc(): int
    {
        $removed = 0;
        $alive = fn (string $id): bool => (bool) $this->redis()->exists($this->subKey($id));

        foreach ($this->members('rq:index:tables') as $table) {
            $keys = ["rq:dep:t:{$table}"];
            foreach ($this->predicateKeys($table) as $predicate) {
                [$column, $value] = explode(':', $predicate, 2) + [1 => ''];
                $keys[] = $this->predicateKey($table, $column, $value);
            }

            foreach ($keys as $key) {
                foreach ($this->members($key) as $id) {
                    if (! $alive($id)) {
                        $this->redis()->srem($key, $id);
                        $removed++;
                    }
                }
            }
        }

        foreach ($this->members('rq:index:users') as $userId) {
            foreach ($this->idsForUser($userId) as $id) {
                if (! $alive($id)) {
                    $this->redis()->srem("rq:user:{$userId}", $id);
                    $removed++;
                }
            }
        }

        foreach ($this->members('rq:index:subs') as $id) {
            if (! $alive($id)) {
                $this->redis()->srem('rq:index:subs', $id);
            }
        }

        return $removed;
    }

    public function incrementMetric(string $name, int $by = 1): void
    {
        $this->redis()->hincrby('rq:metrics', $name, $by);
    }

    public function recordLatency(float $milliseconds): void
    {
        $this->redis()->lpush('rq:latency', (string) round($milliseconds, 2));
        $this->redis()->ltrim('rq:latency', 0, self::LATENCY_SAMPLES - 1);
    }

    public function stats(): array
    {
        $subscriptions = 0;
        foreach ($this->members('rq:index:subs') as $id) {
            if ((bool) $this->redis()->exists($this->subKey($id))) {
                $subscriptions++;
            }
        }

        $users = 0;
        foreach ($this->members('rq:index:users') as $userId) {
            if ((int) $this->redis()->scard("rq:user:{$userId}") > 0) {
                $users++;
            }
        }

        /** @var array<string, string> $metrics */
        $metrics = $this->redis()->hgetall('rq:metrics') ?: [];
        /** @var array<int, string> $latencies */
        $latencies = $this->redis()->lrange('rq:latency', 0, -1) ?: [];

        return [
            'subscriptions' => $subscriptions,
            'users' => $users,
            'metrics' => array_map('intval', $metrics),
            'latencies' => array_map('floatval', $latencies),
        ];
    }

    public function flush(): void
    {
        foreach ($this->members('rq:index:subs') as $id) {
            $this->redis()->del($this->subKey($id), "rq:debounce:{$id}", "rq:lock:{$id}");
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

        $this->redis()->del('rq:index:subs', 'rq:index:tables', 'rq:index:users', 'rq:metrics', 'rq:latency', 'reactive:mutation_id');
    }

    private function addDeps(Subscription $subscription): void
    {
        foreach ($subscription->tableLevelTables() as $table) {
            $this->redis()->sadd("rq:dep:t:{$table}", $subscription->id);
            $this->redis()->sadd('rq:index:tables', $table);
        }

        foreach ($subscription->predicateDeps() as $dep) {
            $this->redis()->sadd($this->predicateKey($dep->table, (string) $dep->column, (string) $dep->value), $subscription->id);
            $this->redis()->sadd("rq:dep:pk:{$dep->table}", "{$dep->column}:{$dep->value}");
            $this->redis()->sadd('rq:index:tables', $dep->table);
        }
    }

    private function removeDeps(Subscription $subscription): void
    {
        foreach ($subscription->tableLevelTables() as $table) {
            $this->redis()->srem("rq:dep:t:{$table}", $subscription->id);
        }

        foreach ($subscription->predicateDeps() as $dep) {
            $key = $this->predicateKey($dep->table, (string) $dep->column, (string) $dep->value);
            $this->redis()->srem($key, $subscription->id);

            if ((int) $this->redis()->scard($key) === 0) {
                $this->redis()->srem("rq:dep:pk:{$dep->table}", "{$dep->column}:{$dep->value}");
            }
        }
    }

    private function setNx(string $key, int $milliseconds): bool
    {
        $connection = $this->redis();
        $arguments = [self::SET_NX_PX, 1, $key, '1', (string) $milliseconds];

        // phpredis and predis take EVAL arguments in a different order; the
        // Laravel wrapper normalises phpredis, predis speaks Redis natively.
        $result = $connection instanceof PhpRedisConnection
            ? $connection->eval(...$arguments)
            : $connection->command('eval', $arguments);

        return (int) $result === 1;
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
