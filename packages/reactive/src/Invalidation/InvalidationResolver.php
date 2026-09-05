<?php

declare(strict_types=1);

namespace Kit\Reactive\Invalidation;

use Kit\Reactive\Contracts\Registry;

/**
 * Which computations does a batch of changes touch? The table-level set,
 * plus every predicate set whose column:value matches the row before or
 * after the change. A table-wide change matches all predicate sets.
 *
 * Computations, not subscriptions: a hundred tabs watching the same query
 * with the same args resolve to one key and so to one recompute.
 */
final class InvalidationResolver
{
    public function __construct(
        private readonly Registry $registry,
    ) {}

    /**
     * @param  array<int, Change>  $changes
     * @return array<int, string> computation keys
     */
    public function resolve(array $changes): array
    {
        $keys = [];

        foreach ($changes as $change) {
            foreach ($this->registry->keysForTable($change->table) as $key) {
                $keys[$key] = true;
            }

            foreach ($this->registry->predicateKeys($change->table) as $predicate) {
                [$column, $value] = explode(':', $predicate, 2) + [1 => ''];

                if (! $change->isTableWide() && ! in_array($value, $change->valuesFor($column), true)) {
                    continue;
                }

                foreach ($this->registry->keysForPredicate($change->table, $column, $value) as $key) {
                    $keys[$key] = true;
                }
            }
        }

        return array_keys($keys);
    }
}
