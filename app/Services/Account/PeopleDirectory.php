<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Data\PersonData;
use App\Models\Invite;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
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
 */
class PeopleDirectory
{
    /** @var list<string> Allow list, not the request's word: the value reaches orderBy(). */
    private const SORTABLE = ['name', 'email', 'created_at'];

    /**
     * @param  array{search?: string, role?: string, status?: string, sort?: string, direction?: string}  $filters
     * @return array{paginator: LengthAwarePaginator<int, PersonData>, counts: array{active: int, pending: int, total: int}}
     */
    public function paginate(User $viewer, array $filters, int $perPage): array
    {
        $search = trim($filters['search'] ?? '');
        $roleId = $this->roleId($filters['role'] ?? '');
        $status = in_array($filters['status'] ?? '', ['active', 'pending'], true) ? $filters['status'] : 'all';

        $sort = in_array($filters['sort'] ?? '', self::SORTABLE, true) ? $filters['sort'] : 'name';
        $column = $sort === 'name' ? 'sort_name' : $sort;
        $direction = ($filters['direction'] ?? '') === 'desc' ? 'desc' : 'asc';

        $seesUsers = $viewer->can('viewAny', User::class);
        $seesInvites = $viewer->can('viewAny', Invite::class);

        // Counts ignore the segment on purpose: the tab badges must not change
        // when you switch tabs.
        $counts = [
            'active' => $seesUsers ? $this->users($search, $roleId)->count() : 0,
            'pending' => $seesInvites ? $this->invites($search, $roleId)->count() : 0,
        ];
        $counts['total'] = $counts['active'] + $counts['pending'];

        $branches = array_values(array_filter([
            $seesUsers && $status !== 'pending' ? $this->users($search, $roleId) : null,
            $seesInvites && $status !== 'active' ? $this->invites($search, $roleId) : null,
        ]));

        if ($branches === []) {
            return ['paginator' => new LengthAwarePaginator([], 0, $perPage), 'counts' => $counts];
        }

        $union = array_shift($branches);

        foreach ($branches as $branch) {
            $union->unionAll($branch);
        }

        // Case-folded: SQLite and Postgres sort upper case before lower by
        // default, which puts "Zoe" above "bob" and reads as a broken list.
        // $column comes from the allow list above, never from the request.
        $order = in_array($column, ['sort_name', 'email'], true) ? DB::raw("lower({$column})") : $column;

        $page = DB::query()
            ->fromSub($union, 'people')
            ->orderBy($order, $direction)
            // ULIDs are unique and creation-ordered, so this only settles ties.
            ->orderBy('id')
            ->paginate($perPage);

        return ['paginator' => $this->hydrate($page, $viewer), 'counts' => $counts];
    }

    /**
     * Swap the raw union rows for full PersonData. Two queries for the whole
     * page, one per kind, rather than one per row.
     *
     * @param  LengthAwarePaginator<int, object>  $page
     * @return LengthAwarePaginator<int, PersonData>
     */
    private function hydrate(LengthAwarePaginator $page, User $viewer): LengthAwarePaginator
    {
        $rows = collect($page->items());
        $idsOf = fn (string $kind): array => $rows->where('kind', $kind)->pluck('id')->map(strval(...))->all();

        $users = User::query()->with('role')->findMany($idsOf('user'))->keyBy('id');
        $invites = Invite::query()->with(['role', 'inviter'])->findMany($idsOf('invite'))->keyBy('id');

        $items = $rows->map(function (object $row) use ($users, $invites, $viewer): PersonData {
            $id = (string) $row->id;

            return $row->kind === 'user'
                ? PersonData::fromUser($users->get($id), $viewer)
                : PersonData::fromInvite($invites->get($id), $viewer);
        })->all();

        return new LengthAwarePaginator(
            $items,
            $page->total(),
            $page->perPage(),
            $page->currentPage(),
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    private function users(string $search, ?string $roleId): Builder
    {
        return $this->filter(
            DB::table('users')->select([
                DB::raw("'user' as kind"),
                'id',
                DB::raw('name as sort_name'),
                'email',
                'role_id',
                'created_at',
            ]),
            $search,
            $roleId,
            'name',
        );
    }

    private function invites(string $search, ?string $roleId): Builder
    {
        return $this->filter(
            DB::table('invites')
                ->select([
                    DB::raw("'invite' as kind"),
                    'id',
                    DB::raw('email as sort_name'),
                    'email',
                    'role_id',
                    'created_at',
                ])
                ->whereNull('accepted_at')
                ->whereNull('declined_at'),
            $search,
            $roleId,
            'email',
        );
    }

    /**
     * Filters run inside each branch, before the union, so the indexes on
     * `users.email` and `invites.email` still apply.
     */
    private function filter(Builder $query, string $search, ?string $roleId, string $nameColumn): Builder
    {
        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';

            $query->where(function (Builder $inner) use ($term, $nameColumn): void {
                $inner->where($nameColumn, 'like', $term)->orWhere('email', 'like', $term);
            });
        }

        if ($roleId !== null) {
            $query->where('role_id', $roleId);
        }

        return $query;
    }

    private function roleId(string $key): ?string
    {
        if ($key === '') {
            return null;
        }

        return Role::query()->where('key', $key)->value('id');
    }
}
