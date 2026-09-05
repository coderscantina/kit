<?php

declare(strict_types=1);

namespace Kit\Reactive\Registry;

use Kit\Reactive\Contracts\Registry;

/**
 * In-memory registry for the sqlite test suite and for `Reactive::fake()`.
 * Same semantics as the Redis one, minus TTLs: nothing here expires.
 */
final class ArrayRegistry implements Registry
{
    /** @var array<string, Subscription> */
    private array $subscriptions = [];

    /** @var array<string, array<string, true>> */
    private array $tableSets = [];

    /** @var array<string, array<string, true>> keyed "table|col|val" */
    private array $predicateSets = [];

    /** @var array<string, array<string, true>> */
    private array $predicateKeys = [];

    /** @var array<string, array<string, true>> */
    private array $userSets = [];

    /** @var array<string, float> id => expires-at microtime */
    private array $debounces = [];

    private int $mutationId = 0;

    /** @var array<string, int> */
    private array $metrics = [];

    /** @var array<int, float> */
    private array $latencies = [];

    public function put(Subscription $subscription): void
    {
        $this->subscriptions[$subscription->id] = $subscription;
        $this->userSets[$subscription->userId][$subscription->id] = true;
        $this->syncDeps($subscription);
    }

    public function get(string $id): ?Subscription
    {
        $subscription = $this->subscriptions[$id] ?? null;

        return $subscription === null ? null : clone $subscription;
    }

    public function delete(string $id): void
    {
        $subscription = $this->subscriptions[$id] ?? null;

        if ($subscription === null) {
            return;
        }

        $this->clearDeps($subscription);
        unset($this->userSets[$subscription->userId][$id], $this->subscriptions[$id]);
    }

    public function touch(string $id): void {}

    public function updateResult(string $id, string $hash, int $mutationId): void
    {
        if (isset($this->subscriptions[$id])) {
            $this->subscriptions[$id]->resultHash = $hash;
            $this->subscriptions[$id]->lastMutationId = $mutationId;
        }
    }

    public function syncDeps(Subscription $subscription): void
    {
        $stored = $this->subscriptions[$subscription->id] ?? null;

        if ($stored !== null) {
            $this->clearDeps($stored);
            $stored->tables = $subscription->tables;
            $stored->deps = $subscription->deps;
        }

        foreach ($subscription->tableLevelTables() as $table) {
            $this->tableSets[$table][$subscription->id] = true;
        }

        foreach ($subscription->predicateDeps() as $dep) {
            $this->predicateSets[$this->predicateKey($dep->table, (string) $dep->column, (string) $dep->value)][$subscription->id] = true;
            $this->predicateKeys[$dep->table]["{$dep->column}:{$dep->value}"] = true;
        }
    }

    public function purgeUser(string $userId): void
    {
        foreach ($this->idsForUser($userId) as $id) {
            $this->delete($id);
        }
    }

    public function countForUser(string $userId): int
    {
        return count($this->userSets[$userId] ?? []);
    }

    public function idsForUser(string $userId): array
    {
        return array_keys($this->userSets[$userId] ?? []);
    }

    public function idsForTable(string $table): array
    {
        return array_keys($this->tableSets[$table] ?? []);
    }

    public function predicateKeys(string $table): array
    {
        return array_keys($this->predicateKeys[$table] ?? []);
    }

    public function idsForPredicate(string $table, string $column, string $value): array
    {
        return array_keys($this->predicateSets[$this->predicateKey($table, $column, $value)] ?? []);
    }

    public function nextMutationId(): int
    {
        return ++$this->mutationId;
    }

    public function currentMutationId(): int
    {
        return $this->mutationId;
    }

    public function debounce(string $id, int $milliseconds): bool
    {
        $now = microtime(true);

        if (($this->debounces[$id] ?? 0) > $now) {
            return false;
        }

        $this->debounces[$id] = $now + $milliseconds / 1000;

        return true;
    }

    public function releaseDebounce(string $id): void
    {
        unset($this->debounces[$id]);
    }

    public function withLock(string $id, callable $callback): mixed
    {
        return $callback();
    }

    public function gc(): int
    {
        return 0;
    }

    public function incrementMetric(string $name, int $by = 1): void
    {
        $this->metrics[$name] = ($this->metrics[$name] ?? 0) + $by;
    }

    public function recordLatency(float $milliseconds): void
    {
        $this->latencies[] = $milliseconds;
        $this->latencies = array_slice($this->latencies, -200);
    }

    public function stats(): array
    {
        return [
            'subscriptions' => count($this->subscriptions),
            'users' => count(array_filter($this->userSets, fn (array $ids) => $ids !== [])),
            'metrics' => $this->metrics,
            'latencies' => $this->latencies,
        ];
    }

    public function flush(): void
    {
        $this->subscriptions = [];
        $this->tableSets = [];
        $this->predicateSets = [];
        $this->predicateKeys = [];
        $this->userSets = [];
        $this->debounces = [];
        $this->mutationId = 0;
        $this->metrics = [];
        $this->latencies = [];
    }

    private function clearDeps(Subscription $subscription): void
    {
        foreach ($subscription->tableLevelTables() as $table) {
            unset($this->tableSets[$table][$subscription->id]);
        }

        foreach ($subscription->predicateDeps() as $dep) {
            $key = $this->predicateKey($dep->table, (string) $dep->column, (string) $dep->value);
            unset($this->predicateSets[$key][$subscription->id]);

            if (($this->predicateSets[$key] ?? []) === []) {
                unset($this->predicateSets[$key], $this->predicateKeys[$dep->table]["{$dep->column}:{$dep->value}"]);
            }
        }
    }

    private function predicateKey(string $table, string $column, string $value): string
    {
        return "{$table}|{$column}|{$value}";
    }
}
