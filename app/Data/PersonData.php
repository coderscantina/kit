<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Invite;
use App\Models\User;
use App\Services\Account\AvatarStorage;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the people list, which holds accounts and outstanding invitations
 * side by side. `kind` says which actions a row has; `state` says which badge
 * it wears. The two are separate axes: an expired invitation is still an
 * invitation.
 *
 * The permission flags are decided here rather than in the client, so the row
 * component stays dumb and the root and last-owner cases cannot drift.
 */
#[TypeScript]
class PersonData extends Data
{
    public function __construct(
        /** `user` or `invite`. */
        public string $kind,
        public string $id,
        public ?string $name,
        public string $email,
        public ?string $role,
        public ?string $avatarUrl,
        /** `active`, `pending` or `expired`. */
        public string $state,
        public bool $isRoot,
        public bool $emailVerified,
        public bool $twoFactorEnabled,
        public ?string $lastLoginAt,
        public ?string $invitedBy,
        public ?string $expiresAt,
        public string $createdAt,
        public bool $canAssignRole,
        public bool $canRemove,
        public bool $canResend,
    ) {}

    public static function fromUser(User $user, User $viewer): self
    {
        return new self(
            kind: 'user',
            id: $user->id,
            name: $user->name,
            email: $user->email,
            role: $user->role?->key,
            avatarUrl: AvatarStorage::url($user),
            state: 'active',
            isRoot: $user->is_root,
            emailVerified: $user->email_verified_at !== null,
            twoFactorEnabled: $user->hasEnabledTwoFactor(),
            lastLoginAt: $user->last_login_at?->toIso8601String(),
            invitedBy: null,
            expiresAt: null,
            createdAt: $user->created_at?->toIso8601String() ?? '',
            canAssignRole: $viewer->can('changeRole', $user),
            canRemove: $viewer->can('delete', $user),
            canResend: false,
        );
    }

    public static function fromInvite(Invite $invite, User $viewer): self
    {
        $manageable = $viewer->can('delete', $invite);

        return new self(
            kind: 'invite',
            id: $invite->id,
            name: null,
            email: $invite->email,
            role: $invite->role?->key,
            avatarUrl: null,
            state: $invite->expires_at->isPast() ? 'expired' : 'pending',
            isRoot: false,
            emailVerified: false,
            twoFactorEnabled: false,
            lastLoginAt: null,
            invitedBy: $invite->inviter?->name,
            expiresAt: $invite->expires_at->toIso8601String(),
            createdAt: $invite->created_at?->toIso8601String() ?? '',
            canAssignRole: false,
            canRemove: $manageable,
            canResend: $manageable,
        );
    }
}
