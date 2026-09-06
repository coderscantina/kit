<?php

declare(strict_types=1);

namespace App\Support\Filtering;

/**
 * The `+column,-column` sort string the lists speak, parsed against an allow
 * list.
 *
 * The wire format is the one `coderscantina/laravel-filter` reads, so an
 * Eloquent list hands the string straight to its filter and a hand-built
 * query (the people union) parses it here instead of inventing a second
 * `sort=`/`direction=` pair. Anything not on the allow list is dropped: the
 * column reaches `orderBy()`.
 */
final class SortString
{
    /**
     * @param  list<string>  $allowed
     * @return list<array{column: string, direction: 'asc'|'desc'}>
     */
    public static function parse(string $sort, array $allowed, int $max = 3): array
    {
        $parsed = [];

        foreach (explode(',', $sort) as $item) {
            $item = trim($item);

            if ($item === '' || count($parsed) >= $max) {
                continue;
            }

            $direction = str_starts_with($item, '-') ? 'desc' : 'asc';
            $column = ltrim($item, '+-');

            if (! in_array($column, $allowed, true)) {
                continue;
            }

            $parsed[] = ['column' => $column, 'direction' => $direction];
        }

        return $parsed;
    }

    /**
     * The first parsed pair, or the given fallback when the string names
     * nothing sortable. Lists with a single order column use this.
     *
     * @param  list<string>  $allowed
     * @return array{column: string, direction: 'asc'|'desc'}
     */
    public static function first(string $sort, array $allowed, string $column, string $direction = 'asc'): array
    {
        return self::parse($sort, $allowed, 1)[0] ?? [
            'column' => $column,
            'direction' => $direction === 'desc' ? 'desc' : 'asc',
        ];
    }
}
