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
    /** @var array<string, Computation> */
    private array $computations = [];

    /** @var array<string, Subscription> */
    private array $subscriptions = [];

    /** @var array<string, array<string, true>> computation key => subscription ids */
    private array $watchers = [];

    /** @var array<string, array<string, true>> table => computation keys */
    private array $tableSets = [];

    /** @var array<string, array<string, true>> keyed "table|col|val" => computation keys */
    private array $predicateSets = [];

    /** @var array<string, array<string, true>> */
    private array $predicateKeys = [];

    /** @var array<string, array<string, true>> */
    private array $userSets = [];

    /** @var array<string, float> key => expires-at microtime */
    private array $debounces = [];

    private int $mutationId = 0;

    public function putComputation(Computation $computation): void
    {
        $this->computations[$computation->key] = $computation;
        $this->watchers[$computation->key] ??= [];
        $this->addDeps($computation);
    }

    public function computation(string $key): ?Computation
    {
        $computation = $this->computations[$key] ?? null;

        return $computation === null ? null : clone $computation;
    }

    public function updateComputation(string $key, string $hash, string $result, int $mutationId): void
    {
        if (! isset($this->computations[$key])) {
            return;
        }

        $this->computations[$key]->resultHash = $hash;
        $this->computations[$key]->result = $result;
        $this->computations[$key]->lastMutationId = $mutationId;
    }

    public function touchComputation(string $key, int $mutationId): void
    {
        if (isset($this->computations[$key])) {
            $this->computations[$key]->lastMutationId = $mutationId;
        }
    }

    public function syncDeps(Computation $computation): void
    {
        $stored = $this->computations[$computation->key] ?? null;

        if ($stored !== null) {
            $this->clearDeps($stored);
            $stored->tables = $computation->tables;
            $stored->deps = $computation->deps;
        }

        $this->addDeps($computation);
    }

    public function forgetComputation(string $key): void
    {
        $computation = $this->computations[$key] ?? null;

        if ($computation !== null) {
            $this->clearDeps($computation);
        }

        unset($this->computations[$key], $this->watchers[$key], $this->debounces[$key]);
    }

    public function subscribersOf(string $key): array
    {
        return array_keys($this->watchers[$key] ?? []);
    }

    public function put(Subscription $subscription): void
    {
        $this->subscriptions[$subscription->id] = $subscription;
        $this->watchers[$subscription->computationKey][$subscription->id] = true;
        $this->userSets[$subscription->userId][$subscription->id] = true;
    }

    public function get(string $id): ?Subscription
    {
        return $this->subscriptions[$id] ?? null;
    }

    public function delete(string $id): void
    {
        $subscription = $this->subscriptions[$id] ?? null;

        if ($subscription === null) {
            return;
        }

        unset(
            $this->subscriptions[$id],
            $this->userSets[$subscription->userId][$id],
            $this->watchers[$subscription->computationKey][$id],
        );

        if (($this->watchers[$subscription->computationKey] ?? []) === []) {
            $this->forgetComputation($subscription->computationKey);
        }
    }

    public function touch(string $id): void {}

    public function purgeUser(string $userId): void
    {
        foreach ($this->idsForUser($userId) as $id) {
            $this->delete($id);
        }

        unset($this->userSets[$userId]);
    }

    public function countForUser(string $userId): int
    {
        return count($this->userSets[$userId] ?? []);
    }

    public function idsForUser(string $userId): array
    {
        return array_keys($this->userSets[$userId] ?? []);
    }

    public function keysForTable(string $table): array
    {
        return array_keys($this->tableSets[$table] ?? []);
    }

    public function predicateKeys(string $table): array
    {
        return array_keys($this->predicateKeys[$table] ?? []);
    }

    public function keysForPredicate(string $table, string $column, string $value): array
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

    public function debounce(string $key, int $milliseconds): bool
    {
        $now = microtime(true);

        if (($this->debounces[$key] ?? 0) > $now) {
            return false;
        }

        $this->debounces[$key] = $now + $milliseconds / 1000;

        return true;
    }

    public function releaseDebounce(string $key): void
    {
        unset($this->debounces[$key]);
    }

    /**
     * Nothing is concurrent in a single process, so the lock is always free
     * and the callback always runs.
     */
    public function withLock(string $key, callable $callback, ?int $waitMs = null): mixed
    {
        return $callback();
    }

    public function gc(): int
    {
        return 0;
    }

    public function stats(): array
    {
        return [
            'subscriptions' => count($this->subscriptions),
            'computations' => count($this->computations),
            'users' => count(array_filter($this->userSets, fn (array $ids) => $ids !== [])),
        ];
    }

    public function flush(): void
    {
        $this->computations = [];
        $this->subscriptions = [];
        $this->watchers = [];
        $this->tableSets = [];
        $this->predicateSets = [];
        $this->predicateKeys = [];
        $this->userSets = [];
        $this->debounces = [];
        $this->mutationId = 0;
    }

    private function addDeps(Computation $computation): void
    {
        foreach ($computation->tableLevelTables() as $table) {
            $this->tableSets[$table][$computation->key] = true;
        }

        foreach ($computation->predicateDeps() as $dep) {
            $this->predicateSets[$this->predicateKey($dep->table, (string) $dep->column, (string) $dep->value)][$computation->key] = true;
            $this->predicateKeys[$dep->table]["{$dep->column}:{$dep->value}"] = true;
        }
    }

    private function clearDeps(Computation $computation): void
    {
        foreach ($computation->tableLevelTables() as $table) {
            unset($this->tableSets[$table][$computation->key]);
        }

        foreach ($computation->predicateDeps() as $dep) {
            $key = $this->predicateKey($dep->table, (string) $dep->column, (string) $dep->value);
            unset($this->predicateSets[$key][$computation->key]);

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
