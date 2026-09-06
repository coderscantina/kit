<?php

declare(strict_types=1);

namespace App\Http\Filters;

use App\Models\Role;
use CodersCantina\Filter\AdvancedFilter;

/**
 * The account list's filters. Every public method here is a query parameter
 * the client may send; `sort` comes from the package's Sortable trait and is
 * bounded by $sortableColumns.
 *
 * The `q` free-text term is deliberately separate from the per-field filters:
 * the search box types into `q`, the filter chips write `name`, `email` and
 * friends, and the two combine with AND.
 */
class UserFilter extends AdvancedFilter
{
    /** @var list<string> */
    protected array $sortableColumns = ['name', 'email', 'created_at', 'last_login_at'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(array $filters)
    {
        parent::__construct($filters);

        // The whole query bag arrives here, so the accepted names are stated
        // rather than inferred: `limit` and `offset` are inherited helpers
        // that would otherwise let a client rewrite the pagination.
        $this->setWhitelistedFilters([
            'q', 'name', 'email', 'role', 'created_at', 'last_login_at', 'two_factor', 'verified', 'sort',
        ]);
    }

    public function q(mixed $value): void
    {
        $term = trim((string) $value);

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $this->builder->where(function ($query) use ($like): void {
            $query->where('name', 'like', $like)->orWhere('email', 'like', $like);
        });
    }

    public function name(mixed $value): void
    {
        $this->applyDynamicFilter('name', $value);
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

    public function last_login_at(mixed $value): void
    {
        $this->applyAdvancedDateFilter('last_login_at', $value);
    }

    /** `true` keeps confirmed second factors only, `false` the accounts without one. */
    public function two_factor(mixed $value): void
    {
        filter_var($value, FILTER_VALIDATE_BOOLEAN)
            ? $this->builder->whereNotNull('two_factor_confirmed_at')
            : $this->builder->whereNull('two_factor_confirmed_at');
    }

    public function verified(mixed $value): void
    {
        filter_var($value, FILTER_VALIDATE_BOOLEAN)
            ? $this->builder->whereNotNull('email_verified_at')
            : $this->builder->whereNull('email_verified_at');
    }
}
