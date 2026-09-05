<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use App\Services\Auth\MembershipGuard;
use Illuminate\Support\Facades\DB;

class ChangeUserRole
{
    public function __construct(
        private readonly MembershipGuard $guard,
        private readonly AuthorizationService $authorization,
    ) {}

    public function execute(User $user, Role $role): User
    {
        DB::transaction(function () use ($user, $role) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($locked->role_id === $role->id) {
                return;
            }

            $this->guard->ensureNotLastOwner($locked);

            $locked->role_id = $role->id;
            $locked->save();
        });

        $this->authorization->invalidateUser($user);

        return $user->refresh();
    }
}
