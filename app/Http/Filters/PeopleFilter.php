<?php

declare(strict_types=1);

namespace App\Http\Filters;

use App\Models\Role;
use App\Services\Account\PeopleDirectory;
use CodersCantina\Filter\AdvancedFilter;

/**
 * The people list's filters, applied to each branch of the union before it is
 * joined, so the indexes on `users.email` and `invites.email` still apply.
 *
 * Sorting is not part of this filter: the union has no model to resolve a
 * relation against and is ordered by PeopleDirectory through SortString.
 *
 * @see PeopleDirectory
 */
class PeopleFilter extends AdvancedFilter
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  string  $nameColumn  `name` on the accounts branch, `email` on the invitations branch.
     */
    public function __construct(array $filters, private readonly string $nameColumn)
    {
        parent::__construct($filters);

        // The whole query bag arrives here, so the accepted names are stated
        // rather than inferred: without this `limit=1` or `sort=` would reach
        // the inherited helpers, and the union has no model for sorting.
        $this->setWhitelistedFilters(['q', 'email', 'role', 'created_at']);
    }

    public function q(mixed $value): void
    {
        $term = trim((string) $value);

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $this->builder->where(function ($query) use ($like): void {
            $query->where($this->nameColumn, 'like', $like)->orWhere('email', 'like', $like);
        });
    }

    public function email(mixed $value): void
    {
        $this->applyDynamicFilter('email', $value);
    }

    /** Role keys, never ids: the key is what the URL and the UI carry. */
    public function role(mixed $value): void
    {
        $this->applyDynamicFilter('role_id', $value, fn (mixed $key): ?string => Role::query()
            ->where('key', (string) $key)
            ->value('id'));
    }

    public function created_at(mixed $value): void
    {
        $this->applyAdvancedDateFilter('created_at', $value);
    }
}
