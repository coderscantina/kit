<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Actions\Users\ChangeUserRole;
use App\Actions\Users\DeleteUser;
use App\Data\RoleData;
use App\Data\UserData;
use App\Http\Controllers\Controller;
use App\Http\Filters\UserFilter;
use App\Http\Requests\Users\UpdateUserRoleRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $this->authorize('viewAny', User::class);

        $filter = new UserFilter($request->query());

        $users = User::query()->with('role')->filter($filter);

        // A list with no ORDER BY is a list whose page 2 may repeat page 1,
        // and `sort=` naming a column that is not sortable leaves the filter
        // with nothing applied.
        if ($filter->getSortColumns() === []) {
            $users->orderBy('name');
        }

        $users = $users->paginate($this->perPage($request))->withQueryString();

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
