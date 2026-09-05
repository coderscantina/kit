<?php

declare(strict_types=1);

namespace Kit\Reactive;

/**
 * A declared read dependency. A query that lists `Dep::eq('messages',
 * 'channel_id', $id)` is only recomputed when a row with that channel_id
 * changes, instead of on every write to the table. Declare predicates on
 * tables over 10k rows or with high write fan-out; leave small tables to
 * the automatic table-level tracking.
 */
final class Dep
{
    private function __construct(
        public readonly string $table,
        public readonly ?string $column,
        public readonly ?string $value,
    ) {}

    public static function eq(string $table, string $column, string|int|bool|null $value): self
    {
        return new self($table, $column, self::normalize($value));
    }

    /**
     * An explicit table-level dependency, for reads the SQL tracker cannot
     * see (a cache, a raw statement, an external lookup keyed on a table).
     */
    public static function table(string $table): self
    {
        return new self($table, null, null);
    }

    public function isPredicate(): bool
    {
        return $this->column !== null;
    }

    /**
     * Values are compared as strings on both sides of the registry so that
     * `1`, `"1"` and `true` land in the same set.
     */
    public static function normalize(string|int|float|bool|null $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    /**
     * @return array{table: string, column: string|null, value: string|null}
     */
    public function toArray(): array
    {
        return ['table' => $this->table, 'column' => $this->column, 'value' => $this->value];
    }

    /**
     * @param  array{table: string, column: string|null, value: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['table'], $data['column'], $data['value']);
    }
}
