<?php

declare(strict_types=1);

namespace Kit\Reactive\Registry;

use Kit\Reactive\Dep;
use Kit\Reactive\Runtime\Canonical;

/**
 * One query run with one set of args, and its last result.
 *
 * A query's `handle()` receives only the validated args, never the user, so
 * two subscribers asking the same question get the same answer. They share a
 * computation: the SQL runs once per change, not once per subscriber, and the
 * dependency sets hold computation keys rather than subscription ids.
 *
 * Authorization is the part that is per user, so it stays on the subscription
 * (§4.4). Nothing here may be pushed to anyone whose `authorize()` has not
 * been re-checked against this result.
 */
final class Computation
{
    /**
     * @param  array<string, mixed>  $args
     * @param  array<int, string>  $tables
     * @param  array<int, Dep>  $deps
     */
    public function __construct(
        public readonly string $key,
        public readonly string $query,
        public readonly array $args,
        public string $resultHash,
        public string $result,
        public int $lastMutationId,
        public readonly int $createdAt,
        public array $tables = [],
        public array $deps = [],
        public ?LastRecompute $lastRecompute = null,
    ) {}

    /**
     * The identity of a computation: its query name and its canonical args.
     * Canonical, so `{a: 1, b: 2}` and `{b: 2, a: 1}` are one computation.
     *
     * @param  array<string, mixed>  $args
     */
    public static function keyFor(string $query, array $args): string
    {
        return hash('xxh3', $query.'|'.Canonical::encode($args));
    }

    /**
     * The stored result, decoded. Serves `/rq/query`, the fallback fetch for
     * results too large to ride inline, and every subscriber after the first.
     */
    public function decodedResult(): mixed
    {
        return json_decode($this->result, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, string>
     */
    public function toHash(): array
    {
        return [
            ...($this->lastRecompute?->toHash() ?? []),
            'query' => $this->query,
            'args' => json_encode($this->args, JSON_THROW_ON_ERROR),
            'result_hash' => $this->resultHash,
            'result' => $this->result,
            'last_mutation_id' => (string) $this->lastMutationId,
            'created_at' => (string) $this->createdAt,
            'tables' => json_encode($this->tables, JSON_THROW_ON_ERROR),
            'deps' => json_encode(array_map(fn (Dep $dep) => $dep->toArray(), $this->deps), JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param  array<string, string>  $hash
     */
    public static function fromHash(string $key, array $hash): self
    {
        /** @var array<string, mixed> $args */
        $args = json_decode($hash['args'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
        /** @var array<int, string> $tables */
        $tables = json_decode($hash['tables'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
        /** @var array<int, array{table: string, column: string|null, value: string|null}> $deps */
        $deps = json_decode($hash['deps'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);

        return new self(
            key: $key,
            query: $hash['query'],
            args: $args,
            resultHash: $hash['result_hash'] ?? '',
            result: $hash['result'] ?? 'null',
            lastMutationId: (int) ($hash['last_mutation_id'] ?? 0),
            createdAt: (int) ($hash['created_at'] ?? 0),
            tables: $tables,
            deps: array_map(fn (array $dep) => Dep::fromArray($dep), $deps),
            lastRecompute: LastRecompute::fromHash($hash),
        );
    }

    /**
     * Tables that get table-level tracking: every tracked table that has no
     * predicate declared on it. Tables with a predicate are only reached
     * through their predicate sets, which is what makes declaring one
     * reduce fan-out.
     *
     * @return array<int, string>
     */
    public function tableLevelTables(): array
    {
        $predicated = [];
        foreach ($this->deps as $dep) {
            if ($dep->isPredicate()) {
                $predicated[$dep->table] = true;
            }
        }

        $explicit = array_map(fn (Dep $dep) => $dep->table, array_filter($this->deps, fn (Dep $dep) => ! $dep->isPredicate()));

        return array_values(array_unique(array_filter(
            [...$this->tables, ...$explicit],
            fn (string $table) => ! isset($predicated[$table]),
        )));
    }

    /**
     * @return array<int, Dep>
     */
    public function predicateDeps(): array
    {
        return array_values(array_filter($this->deps, fn (Dep $dep) => $dep->isPredicate()));
    }
}
