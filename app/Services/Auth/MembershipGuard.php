<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\LastOwnerException;
use App\Models\Role;
use App\Models\User;

/**
 * The last-owner rule: an instance can never lose its last owner, whether
 * by role change, deletion, or self-deletion. Callers run this inside the
 * transaction that performs the change, after locking the row.
 */
class MembershipGuard
{
    public function ensureNotLastOwner(User $user): void
    {
        $ownerRoleId = Role::query()->where('key', Role::OWNER)->value('id');

        if ($ownerRoleId === null || $user->role_id !== $ownerRoleId) {
            return;
        }

        $owners = User::query()->where('role_id', $ownerRoleId)->lockForUpdate()->count();

        if ($owners <= 1) {
            throw new LastOwnerException;
        }
    }
}
