# Lists: filtering, sorting, saved views

Every list surface in the kit — the people page, and whatever you generate
next — speaks one query contract, on both sides of the wire:

| Parameter       | Means                                             | Example                 |
| --------------- | ------------------------------------------------- | ----------------------- |
| `page`          | 1-based page number                               | `page=3`                |
| `per_page`      | rows per page, clamped server-side                | `per_page=50`           |
| `sort`          | `+column` ascending, `-column` descending         | `sort=-created_at`      |
| `q`             | free text, matched against the list's own columns | `q=ada`                 |
| `format`        | on the export endpoint only: `csv`, `xlsx`, `pdf` | `format=xlsx`           |
| _anything else_ | one filter, as `operator:value`                   | `email=like:%40acme.io` |

The URL _is_ the state. A narrowed list can be linked, reloaded, walked back
through with the browser's own buttons — and saved under a name.

## Server side

Filters and sorting come from a filter class, never from request values
spliced into a builder. Kit uses
[`coderscantina/laravel-filter`](https://github.com/coderscantina/laravel-filter);
`App\Models\Model` and `App\Models\User` carry its `Filterable` trait, so every
model has `->filter($filter)`.

`php artisan make:filter Post` writes one from the feature's columns, with a
test for the search, the sort allow list and the whitelist.

```php
namespace App\Http\Filters;

use CodersCantina\Filter\AdvancedFilter;

class PostFilter extends AdvancedFilter
{
    /** The only columns `sort` may name. The value reaches orderBy(). */
    protected array $sortableColumns = ['title', 'created_at'];

    public function __construct(array $filters)
    {
        parent::__construct($filters);

        // State the accepted names. The whole query bag arrives here, and
        // `limit` and `offset` are inherited helpers a client must not reach.
        $this->setWhitelistedFilters(['q', 'title', 'status', 'created_at', 'sort']);
    }

    /** Free text. One method, whatever "search" means for this list. */
    public function q(mixed $value): void
    {
        $like = '%'.addcslashes(trim((string) $value), '%_\\').'%';

        $this->builder->where('title', 'like', $like);
    }

    public function status(mixed $value): void
    {
        $this->applyDynamicFilter('status', $value);
    }

    public function created_at(mixed $value): void
    {
        $this->applyAdvancedDateFilter('created_at', $value);
    }
}
```

Then in the controller:

```php
$filter = new PostFilter($request->query());
$posts = Post::query()->filter($filter);

// A list with no ORDER BY is a list whose page 2 may repeat page 1.
if ($filter->getSortColumns() === []) {
    $posts->orderByDesc('created_at');
}

return PostData::collect($posts->paginate($this->perPage($request))->withQueryString())->toArray();
```

Two rules that are not optional:

- **`setWhitelistedFilters()` on every filter.** Without it any public method
  on the base classes is reachable from the query string.
- **`$sortableColumns` on every filter.** An unlisted column is dropped, not
  passed through.

`tests/Architecture/ListContractTest.php` checks both on every filter, and
fails any page that puts a table inside a `Card`.

`applyDynamicFilter()` accepts the `operator:value` forms below; a bare value
is an equality test. `applyAdvancedDateFilter()` adds `2026-01-01` →
`2026-01-01 00:00:00` normalisation and the `start...end` range form.

### Operators

`eq` `neq` `like` `!like` `^like` (starts with) `like$` (ends with) `lt` `lte`
`gt` `gte` `in` `!in` `null` `!null` `empty` `!empty`.

`in` and `!in` take a comma-separated list: `role=in:owner,admin`.

### A query that is not one builder

The people list is a union of accounts and invitations, which has no model to
sort against. It applies the filter to each branch before the union and parses
the sort string itself with `App\Support\Filtering\SortString`. That helper is
there for any list in the same position; everything else uses the package.

## Client side

`useTableQueryState` owns the URL and produces the request:

```ts
const table = useTableQueryState({
  defaultSort: { column: 'created_at', direction: 'desc' },
  filterKeys: ['status', 'author'],
})

const posts = useQuery({
  queryKey: computed(() => queryKeys.posts(table.params.value)),
  queryFn: () => api.posts.index(table.params.value),
  placeholderData: keepPreviousData,
})
```

`filterKeys` is the list of parameters this table owns. Only those are read
from and written to the URL, so an unrelated parameter on the same page is
left alone and a stale one never reaches the API.

What it hands back:

|                                     |                                                                  |
| ----------------------------------- | ---------------------------------------------------------------- |
| `page`, `perPage`, `sort`, `search` | writable refs, backed by the URL                                 |
| `filters`                           | `Record<string, string>` of chips, also backed by the URL        |
| `params`                            | the settled request bag — use it as the query key                |
| `isFiltered`                        | whether anything narrows the list; drives the empty state's copy |
| `snapshot`                          | the state as a flat bag, which is what a saved view stores       |
| `apply(snapshot)`                   | put a saved view back                                            |

`TableFilter` is the input: type to search, or pick a field and build a chip.
Fields declare a type and get sensible operators for free.

```vue
<TableFilter
  v-model="table.filters.value"
  v-model:search="table.search.value"
  :fields="[
    { id: 'status', label: 'Status', type: 'select', options },
    { id: 'title', label: 'Title', type: 'text' },
    { id: 'created_at', label: 'Created', type: 'date' },
  ]"
/>
```

| Type      | Leads with       | Because                                |
| --------- | ---------------- | -------------------------------------- |
| `text`    | `contains`       | a typed fragment is what someone means |
| `select`  | `is`             | a picked option is an exact answer     |
| `date`    | `is on or after` | dates are asked about as ranges        |
| `boolean` | `is`             | there are two values                   |

Pass `operators` on a field to override the set. A field with exactly one
operator skips the operator step.

Below the `sm` breakpoint the chips collapse into a count that opens a popover
holding the same chips. Two chips are wider than a phone, and inline they
squeeze the input to nothing — and the input is the part that has to keep
working, because you cannot add a filter in a field you cannot type in.

`eq` is written to the URL bare (`role=admin`), everything else carries its
prefix (`email=like:acme`). Both forms are what `applyDynamicFilter()` reads.

## Segments

The coarse cut — everything, or one slice of it — is a row of tabs on a wide
screen, each tab carrying its count, because the whole choice is worth the
width when there is width. Below `40rem`, the breakpoint the rows stack and the
chips collapse at, it becomes one button that says which slice is showing and
how many are in it, with the rest in a menu.

Both forms are rendered and the other is `display: none`. There is no media
query in JavaScript, so the first frame is never the wrong control, and the
hidden form is out of the accessibility tree and out of the tab order. The tabs
are real tabs (`role="tab"`, `aria-selected`); the menu is
`menuitemradio` + `aria-checked`. Both emit the same value.

```vue
<TableSegments
  v-model="segment"
  :segments="[
    { value: 'all', label: t('users.segments.all'), count: counts.total },
    { value: 'active', label: t('users.segments.active'), count: counts.active },
  ]"
/>
```

It writes whatever the caller binds it to, which on the people list is the
same `status` filter the chips write. The URL and a saved view do not know the
difference between a segment and a chip, and that is the point.

## Saved views

A view is a name plus the table's `snapshot`, stored per user and per scope.
Add the menu next to the filter bar:

```ts
const views = useSavedViews({ scope: 'posts', table })
```

```vue
<SavedViews :views="views" :dirty="Object.keys(table.snapshot.value).length > 0" />
```

The trigger is the bookmark alone. Losing the name would lose the "where am
I" answer, so it is kept three other ways: the icon gains a check mark while a
view is applied, the button's accessible name and tooltip become that view's
name, and the menu ticks the row. Shape carries it, not colour.

Applying a view is a navigation: it writes the stored parameters into the URL,
and the list reloads through the normal query path. One view per scope can be
the default, and the server clears the others when one is marked, so two tabs
cannot both believe they hold it.

The REST endpoints are `/api/account/views` (`GET`, `POST`) and
`/api/account/views/{savedView}` (`PATCH`, `DELETE`). Every query is scoped to
the session's user, so there is no policy to forget. A user keeps at most 30
views per scope.

## Export

Every list can be downloaded as it stands: same filters, same sort, `page` and
`per_page` dropped, because an export is of the result set and not of the page
someone happens to be looking at.

The endpoint runs through the list's own query, so the file cannot drift from
the table, and it repeats the list's authorization check rather than inventing
a second one.

```php
Route::get('people/export', [PeopleController::class, 'export'])
    ->middleware('throttle:sensitive')
    ->name('people.export');
```

```php
$format = ExportFormat::from($request->string('format')->toString());
$result = $directory->export($viewer, $request->query(), $format->rowLimit());

return ListExport::stream(
    format: $format,
    basename: 'people',
    title: __('exports.people.title'),
    headings: $headings,
    rows: $this->exportRows($result['rows']),
    headers: $result['total'] > $format->rowLimit() ? ['X-Export-Truncated' => '1'] : [],
);
```

`App\Support\Export\ListExport` knows nothing about people or posts: it takes
column labels and an iterable of scalar rows and writes bytes. Rows arrive as
an iterable, never an array, so the source can be a generator and the whole
result set is never resident.

| Format | Cap    | Why                                                          |
| ------ | ------ | ------------------------------------------------------------ |
| `csv`  | 25 000 | Written row by row straight to the socket, and free to hold. |
| `xlsx` | 10 000 | PhpSpreadsheet finishes the sheet in memory first.           |
| `pdf`  | 2 000  | Dompdf lays every row out before the first byte.             |

25 000 rows is where an export stops being a download and starts being a data
dump; past that it belongs in a queued job with a signed link. When the result
set is longer than the cap the response carries `X-Export-Truncated: 1` and the
client says so rather than handing over a short file in silence.

Two details that are not cosmetic: the CSV is written with a BOM, or Excel
reads a UTF-8 file as Latin-1 and mangles every umlaut; and XLSX cells are
written as explicit strings, or a value beginning with `=` is a formula Excel
will run.

On the client, `TableExport` owns the menu, the busy state and what happens to
the bytes. The caller supplies the request and nothing else:

```vue
<TableExport :download="(format) => api.people.export(table.params.value, format)" />
```

The file is fetched rather than linked. A plain `<a download>` turns a 403 or
a rate limit into an error page in a new tab, and cannot say that something is
happening while a long list is being written.

## Table components

A table draws its own border and radius, and it is never put inside a `Card`.
This reverses an earlier call to leave tables edgeless. Without an edge the
list bled into the page and the header row had nothing to sit against; with
one, the list is a single object and the header's fill reads as a header. The
edge belongs to the table, so a card around it would still be a box inside a
box.

- `Table`, `TableHeader`, `TableRow`, `TableHead`, `TableCell` — the
  primitives. The box sits on the page's content edge, flush with the toolbar
  above it and the pager below. It used to bleed into the gutter by a cell's
  padding so the first column's text lined up with the page heading; once the
  table drew a border, that bleed put its edge outside everything around it,
  and a border that misses its neighbours by 12px is the worse defect. Give
  `Table` a `label`: a table without one is "table" to a screen reader.
- `variant` — `page` (the default) owns its width: its rows stack on a phone.
  `boxed` is a table inside something else, a panel or a field editor, where
  that is not wanted.
- `TableHeader` — a fill one step off the table's surface, closed by the
  stronger rule. The header row takes no hover, because it is not a record.
- `TableSortableHead` — a header that sorts. It is a button and sets
  `aria-sort`, so sorting from the keyboard is the same gesture as with a
  mouse. The arrow is solid on the sorted column and faint on every other
  sortable one, so which columns sort is visible before anything is hovered.
- `TableLoadingRow`, `TableEmptyRow` — skeletons while there is nothing yet,
  and the "nothing here" row afterwards.
- `TablePaginationFooter` — a pager, a counter and a page-size select. It
  draws no rule of its own: the table above it closes its own box, and a
  second rule 12px under the first was two lines saying one thing. It sits on
  the page's content edge, level with the table's outer edge.
  The counter and the select drop away on a phone, where the only question is
  "next".

### On a phone

A wide table on a phone was a sideways scroller, and a column that has
scrolled out of view is a column nobody reads. Below `sm` a `page` table
stacks. A row is a record, not a form: the subject holds the first line on its
own, every field that names a column shares the wrapped line under it, and a
trailing cell that names none carries the row's actions to the right. Rows keep
the table's hairline rather than becoming cards inside its border. The header
row moves out of sight without leaving the accessibility tree, so it is still
announced and its sort buttons are still reachable.

Pass the column's name to the cell and it appears there:

```vue
<TableCell :label="t('users.role')">…</TableCell>
```

It is real text rather than CSS generated content, and on a phone it is hidden
the screen-reader way rather than removed: the phone still announces it, and
the record stays one line of values wide instead of one line of label-value
pairs. The name is also written to `data-label`, which is how the stacked
layout tells a record's secondary fields from its subject and its actions.
Leave the label off the cell that is the row's subject — the name, the title —
and off the actions cell.

Stacking is `display: block`, which strips a table's native semantics, so every
primitive states its ARIA role outright. That is what keeps it a table for a
screen reader here, and it is why the roles look redundant on a wide screen.
