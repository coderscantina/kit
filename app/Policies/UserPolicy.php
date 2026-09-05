<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;

/**
 * Real per-model logic, so this does not extend ResourcePolicy: role changes
 * are owner-only, and nobody manages their own account through here.
 */
class UserPolicy
{
    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->can($user, 'users.view');
    }

    public function changeRole(User $user, User $target): bool
    {
        return $user->id !== $target->id
            && ($user->is_root || $user->hasRole(Role::OWNER))
            && $this->authorization->can($user, 'roles.manage');
    }

    public function delete(User $user, User $target): bool
    {
        return $user->id !== $target->id && $this->authorization->can($user, 'users.manage');
    }

    public function impersonate(User $user, User $target): bool
    {
        return $user->is_root && $user->id !== $target->id && ! $target->is_root;
    }
}
