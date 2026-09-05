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
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('role')
            ->orderBy('name')
            ->paginate($this->perPage($request));

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
