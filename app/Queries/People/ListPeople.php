<?php

declare(strict_types=1);

namespace App\Queries\People;

use App\Data\ListPeopleArgs;
use App\Data\PeoplePageData;
use App\Models\Invite;
use App\Models\User;
use App\Services\Account\PeopleDirectory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Attributes\ReactiveQuery;
use Kit\Reactive\Query;
use Spatie\LaravelData\Data;

/**
 * The people list, live: accounts and outstanding invitations as one page.
 *
 * No `reads()` on purpose: the union touches `users`, `invites` and
 * `roles`, all small, so table-level tracking is the right grain: a sign-up, a
 * role change, an invitation sent or answered wakes the open lists, and a
 * recompute that hashes the same pushes nothing.
 *
 * @extends Query<ListPeopleArgs>
 */
#[ReactiveQuery('people.list', result: PeoplePageData::class)]
final class ListPeople extends Query
{
    public static function args(): string
    {
        return ListPeopleArgs::class;
    }

    /**
     * Each half is gated on its own ability, so a viewer who may see only
     * invitations gets those. Someone who may see neither is refused.
     */
    public function authorize(Authenticatable $user, Data $args): void
    {
        if ($user->getAuthIdentifier() !== $args->viewerId) {
            throw new AuthorizationException;
        }

        $gate = $this->gate($user);

        if ($gate->denies('viewAny', User::class) && $gate->denies('viewAny', Invite::class)) {
            throw new AuthorizationException;
        }
    }

    public function handle(Data $args): mixed
    {
        $viewer = User::query()->findOrFail($args->viewerId);

        $params = $args->params ?? [];
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min((int) ($params['per_page'] ?? 20), 100));

        $result = app(PeopleDirectory::class)->paginate($viewer, $params, $perPage, $page);

        return PeoplePageData::fromPaginator($result['paginator'], $result['counts']);
    }
}
