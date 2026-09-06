<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Actions\Users\ChangeUserRole;
use App\Actions\Users\DeleteUser;
use App\Data\RoleData;
use App\Data\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UpdateUserRoleRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserController extends Controller
{
    /**
     * Columns the client may sort by. An allow list, not the request's word:
     * the value reaches orderBy() and would otherwise be an injection point.
     *
     * @var list<string>
     */
    private const SORTABLE = ['name', 'email', 'created_at', 'last_login_at'];

    /**
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $this->authorize('viewAny', User::class);

        $search = trim($request->string('search')->toString());
        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'name';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';

        $users = User::query()
            ->with('role')
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', $term)->orWhere('email', 'like', $term);
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($this->perPage($request))
            ->withQueryString();

        return UserData::collect($users)->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function roles(): array
    {
        $this->authorize('viewAny', User::class);

        return RoleData::collect(Role::query()->orderByDesc('level')->get())->toArray();
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user, ChangeUserRole $changeUserRole): UserData
    {
        $this->authorize('changeRole', $user);

        $user = $changeUserRole->execute($user, Role::byKey($request->string('role')->toString()));

        return UserData::fromModel($user->load('role'));
    }

    public function destroy(User $user, DeleteUser $deleteUser): Response
    {
        $this->authorize('delete', $user);

        $deleteUser->execute($user);

        return response()->noContent();
    }
}
