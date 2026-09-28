<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Data\PersonData;
use App\Http\Filters\PeopleFilter;
use App\Models\Invite;
use App\Models\User;
use App\Support\Filtering\SortString;
use Generator;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Accounts and outstanding invitations as one list.
 *
 * The merge is a SQL union rather than two requests stitched together in the
 * client, because a page of a merged list has to be a page of the merged list:
 * paginating two sources separately and concatenating them gives a page 2 that
 * skips rows. Only the columns both sides really have are projected, so no
 * branch has to invent a typed NULL for the other's columns; the rows are
 * hydrated from the models afterwards.
 *
 * The table and the export read the same `matching()` query, so a download
 * cannot show a different set of rows than the screen it was started from.
 */
class PeopleDirectory
{
    /** @var list<string> Allow list, not the request's word: the value reaches orderBy(). */
    private const SORTABLE = ['name', 'email', 'created_at'];

    /** Models hydrated per round trip while an export walks the result set. */
    private const HYDRATION_CHUNK = 500;

    /**
     * @param  array<string, mixed>  $filters  The request's query bag: `q`, `role`, `email`, `created_at`, `sort`, `status`.
     * @return array{paginator: LengthAwarePaginator<int, PersonData>, counts: array{active: int, pending: int, total: int}}
     */
    public function paginate(User $viewer, array $filters, int $perPage, ?int $page = null): array
    {
        $counts = $this->counts($viewer, $filters);
        $query = $this->matching($viewer, $filters);

        if ($query === null) {
            return ['paginator' => new LengthAwarePaginator([], 0, $perPage), 'counts' => $counts];
        }

        $rows = $query->paginate($perPage, page: $page);

        return ['paginator' => $this->hydrate($rows, $viewer), 'counts' => $counts];
    }

    /**
     * The same rows the table would show, without pagination and capped.
     *
     * Rows are yielded in chunks, so an export of thousands of people holds
     * one chunk of models at a time rather than the whole list.
     *
     * @param  array<string, mixed>  $filters
     * @return array{rows: iterable<int, PersonData>, total: int}
     */
    public function export(User $viewer, array $filters, int $limit): array
    {
        $query = $this->matching($viewer, $filters);

        if ($query === null) {
            return ['rows' => [], 'total' => 0];
        }

        // Counted before the limit, so the caller can say the file is short.
        $total = (clone $query)->getCountForPagination();

        return ['rows' => $this->rows($query->limit($limit)->get(), $viewer), 'total' => $total];
    }

    /**
     * The ordered union, or null when the viewer may see neither half.
     *
     * @param  array<string, mixed>  $filters
     */
    private function matching(User $viewer, array $filters): ?Builder
    {
        $status = in_array($filters['status'] ?? '', ['active', 'pending'], true)
            ? (string) $filters['status']
            : 'all';

        $branches = array_values(array_filter([
            $viewer->can('viewAny', User::class) && $status !== 'pending' ? $this->users($filters) : null,
            $viewer->can('viewAny', Invite::class) && $status !== 'active' ? $this->invites($filters) : null,
        ]));

        if ($branches === []) {
            return null;
        }

        $union = array_shift($branches);

        foreach ($branches as $branch) {
            $union->unionAll($branch);
        }

        $sort = SortString::first((string) ($filters['sort'] ?? ''), self::SORTABLE, 'name');
        // `name` is projected as `sort_name`, because the invitations branch
        // has no name of its own and sorts by its address instead.
        $column = $sort['column'] === 'name' ? 'sort_name' : $sort['column'];

        // Case-folded: SQLite and Postgres sort upper case before lower by
        // default, which puts "Zoe" above "bob" and reads as a broken list.
        // $column comes from the allow list above, never from the request.
        $order = in_array($column, ['sort_name', 'email'], true) ? DB::raw("lower({$column})") : $column;

        return DB::query()
            ->fromSub($union, 'people')
            ->orderBy($order, $sort['direction'])
            // ULIDs are unique and creation-ordered, so this only settles ties.
            ->orderBy('id');
    }

    /**
     * The tab badges. They ignore the segment on purpose: the counts must not
     * change when you switch tabs.
     *
     * @param  array<string, mixed>  $filters
     * @return array{active: int, pending: int, total: int}
     */
    private function counts(User $viewer, array $filters): array
    {
        $active = $viewer->can('viewAny', User::class) ? $this->users($filters)->count() : 0;
        $pending = $viewer->can('viewAny', Invite::class) ? $this->invites($filters)->count() : 0;

        return ['active' => $active, 'pending' => $pending, 'total' => $active + $pending];
    }

    /**
     * Swap the raw union rows for full PersonData. Two queries for the whole
     * page, one per kind, rather than one per row.
     *
     * @param  LengthAwarePaginator<int, \stdClass>  $page
     * @return LengthAwarePaginator<int, PersonData>
     */
    private function hydrate(LengthAwarePaginator $page, User $viewer): LengthAwarePaginator
    {
        $items = iterator_to_array($this->rows(collect($page->items()), $viewer), false);

        return new LengthAwarePaginator(
            $items,
            $page->total(),
            $page->perPage(),
            $page->currentPage(),
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    /**
     * Union rows to PersonData, two queries per chunk rather than one per row.
     *
     * @param  Collection<int, \stdClass>  $rows
     * @return Generator<int, PersonData>
     */
    private function rows(Collection $rows, User $viewer): Generator
    {
        foreach ($rows->chunk(self::HYDRATION_CHUNK) as $chunk) {
            $idsOf = fn (string $kind): array => $chunk->where('kind', $kind)->pluck('id')->map(strval(...))->all();

            $users = User::query()->with('role')->findMany($idsOf('user'))->keyBy('id');
            $invites = Invite::query()->with(['role', 'inviter'])->findMany($idsOf('invite'))->keyBy('id');

            foreach ($chunk as $row) {
                $id = (string) $row->id;

                yield $row->kind === 'user'
                    ? PersonData::fromUser($users->get($id), $viewer)
                    : PersonData::fromInvite($invites->get($id), $viewer);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function users(array $filters): Builder
    {
        $query = DB::table('users')->select([
            DB::raw("'user' as kind"),
            'id',
            DB::raw('name as sort_name'),
            'email',
            'role_id',
            'created_at',
        ]);

        return $this->filter($query, $filters, 'name');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function invites(array $filters): Builder
    {
        $query = DB::table('invites')
            ->select([
                DB::raw("'invite' as kind"),
                'id',
                DB::raw('email as sort_name'),
                'email',
                'role_id',
                'created_at',
            ])
            ->whereNull('accepted_at')
            ->whereNull('declined_at');

        return $this->filter($query, $filters, 'email');
    }

    /**
     * Filters run inside each branch, before the union, so the indexes on
     * `users.email` and `invites.email` still apply.
     *
     * @param  array<string, mixed>  $filters
     */
    private function filter(Builder $query, array $filters, string $nameColumn): Builder
    {
        $filtered = (new PeopleFilter($filters, $nameColumn))->apply($query);

        /** @var Builder */
        return $filtered;
    }
}
