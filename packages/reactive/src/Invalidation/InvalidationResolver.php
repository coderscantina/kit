<?php

declare(strict_types=1);

namespace Kit\Reactive\Invalidation;

use Kit\Reactive\Contracts\Registry;

/**
 * Which subscriptions does a batch of changes touch? The table-level set,
 * plus every predicate set whose column:value matches the row before or
 * after the change. A table-wide change matches all predicate sets.
 */
final class InvalidationResolver
{
    public function __construct(
        private readonly Registry $registry,
    ) {}

    /**
     * @param  array<int, Change>  $changes
     * @return array<int, string>
     */
    public function resolve(array $changes): array
    {
        $ids = [];

        foreach ($changes as $change) {
            foreach ($this->registry->idsForTable($change->table) as $id) {
                $ids[$id] = true;
            }

            foreach ($this->registry->predicateKeys($change->table) as $key) {
                [$column, $value] = explode(':', $key, 2) + [1 => ''];

                if (! $change->isTableWide() && ! in_array($value, $change->valuesFor($column), true)) {
                    continue;
                }

                foreach ($this->registry->idsForPredicate($change->table, $column, $value) as $id) {
                    $ids[$id] = true;
                }
            }
        }

        return array_keys($ids);
    }
}
