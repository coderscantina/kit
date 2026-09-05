<?php

declare(strict_types=1);

namespace Kit\Reactive\Registry;

use Kit\Reactive\Dep;

final class Subscription
{
    /**
     * @param  array<string, mixed>  $args
     * @param  array<int, string>  $tables
     * @param  array<int, Dep>  $deps
     */
    public function __construct(
        public readonly string $id,
        public readonly string $query,
        public readonly array $args,
        public readonly string $userId,
        public string $resultHash,
        public int $lastMutationId,
        public readonly int $createdAt,
        public array $tables = [],
        public array $deps = [],
    ) {}

    /**
     * @return array<string, string>
     */
    public function toHash(): array
    {
        return [
            'query' => $this->query,
            'args' => json_encode($this->args, JSON_THROW_ON_ERROR),
            'user_id' => $this->userId,
            'result_hash' => $this->resultHash,
            'last_mutation_id' => (string) $this->lastMutationId,
            'created_at' => (string) $this->createdAt,
            'tables' => json_encode($this->tables, JSON_THROW_ON_ERROR),
            'deps' => json_encode(array_map(fn (Dep $dep) => $dep->toArray(), $this->deps), JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param  array<string, string>  $hash
     */
    public static function fromHash(string $id, array $hash): self
    {
        /** @var array<string, mixed> $args */
        $args = json_decode($hash['args'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
        /** @var array<int, string> $tables */
        $tables = json_decode($hash['tables'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
        /** @var array<int, array{table: string, column: string|null, value: string|null}> $deps */
        $deps = json_decode($hash['deps'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);

        return new self(
            id: $id,
            query: $hash['query'],
            args: $args,
            userId: $hash['user_id'],
            resultHash: $hash['result_hash'] ?? '',
            lastMutationId: (int) ($hash['last_mutation_id'] ?? 0),
            createdAt: (int) ($hash['created_at'] ?? 0),
            tables: $tables,
            deps: array_map(fn (array $dep) => Dep::fromArray($dep), $deps),
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
