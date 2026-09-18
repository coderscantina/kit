---
name: list-surfaces
description: The list contract. Use when building or changing a table, a list page, filters, sorting, search, pagination, saved views or exports.
---

# List surfaces

`docs/lists.md` is the full contract. The people page (`resources/js/pages/people`, `PeopleController`, `PeopleFilter`) is the shipped example.

Every list surface speaks one query contract: `page`, `per_page`, `sort` (`+column` / `-column`), the free-text `q`, and one parameter per filter carrying `operator:value`. The URL is the state.

## Server

Filters and sorting come from an `App\Http\Filters\<Name>Filter` extending `CodersCantina\Filter\AdvancedFilter`, applied with `->filter($filter)`. `php artisan make:filter <Feature>` writes one from the feature's columns, with its test. Request values reach the builder only through that class, and `sort` only through its `$sortableColumns` allow list, because the value reaches `orderBy()`. Every filter calls `setWhitelistedFilters()` in its constructor: the whole query bag arrives at `apply()`, and `limit`/`offset` are inherited helpers a client must not reach. `tests/Architecture/ListContractTest.php` checks both.

A hand-built query that has no model to sort against (the people union) parses the string with `App\Support\Filtering\SortString`.

## Client

`useTableQueryState` owns the URL and produces `params`; the API resource passes that bag through rather than re-mapping it. `TableFilter` builds the chips, `SavedViews` stores them.

A table stands on its own: `Table` from `~/components/ui/table` draws its border, sitting flush with the toolbar above and the pager below. The architecture test fails a table inside a `Card`.
