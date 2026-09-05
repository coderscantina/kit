<?php

declare(strict_types=1);

namespace Kit\Reactive\Invalidation;

use Illuminate\Database\Eloquent\Model;
use Kit\Reactive\Dep;

/**
 * One row change: table, key, and the before/after values that predicate
 * matching needs. Values are normalized to strings and long ones dropped, so
 * a job payload stays small and a text column can never be a predicate.
 */
final class Change
{
    private const int MAX_VALUE_LENGTH = 256;

    /**
     * @param  array<string, string>|null  $before
     * @param  array<string, string>|null  $after
     */
    public function __construct(
        public readonly string $table,
        public readonly ?string $id,
        public readonly ?array $before,
        public readonly ?array $after,
    ) {}

    public static function fromModel(Model $model, bool $deleted): self
    {
        return new self(
            table: $model->getTable(),
            id: (string) $model->getKey(),
            // A model created and deleted in one request still says
            // wasRecentlyCreated; a delete always has a before side.
            before: ! $deleted && $model->wasRecentlyCreated ? null : self::scalars($model->getRawOriginal()),
            after: $deleted ? null : self::scalars($model->getAttributes()),
        );
    }

    /** A whole-table invalidation for bulk writes that bypass model events. */
    public static function table(string $table): self
    {
        return new self($table, null, null, null);
    }

    public function isTableWide(): bool
    {
        return $this->id === null;
    }

    /**
     * Values a predicate on the column could have matched before or after.
     *
     * @return array<int, string>
     */
    public function valuesFor(string $column): array
    {
        $values = [];

        foreach ([$this->before, $this->after] as $side) {
            if ($side !== null && array_key_exists($column, $side) && ! in_array($side[$column], $values, true)) {
                $values[] = $side[$column];
            }
        }

        return $values;
    }

    /**
     * @return array{table: string, id: string|null, before: array<string, string>|null, after: array<string, string>|null}
     */
    public function toArray(): array
    {
        return ['table' => $this->table, 'id' => $this->id, 'before' => $this->before, 'after' => $this->after];
    }

    /**
     * @param  array{table: string, id: string|null, before: array<string, string>|null, after: array<string, string>|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['table'], $data['id'], $data['before'], $data['after']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    private static function scalars(array $attributes): array
    {
        $out = [];

        foreach ($attributes as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }

            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $normalized = Dep::normalize($value);

            if (strlen($normalized) <= self::MAX_VALUE_LENGTH) {
                $out[$key] = $normalized;
            }
        }

        return $out;
    }
}
