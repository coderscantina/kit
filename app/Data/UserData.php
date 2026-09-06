<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\User;
use App\Services\Account\AvatarStorage;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class UserData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $locale,
        public ?string $avatarUrl,
        public ?string $role,
        public bool $isRoot,
        public bool $emailVerified,
        public bool $twoFactorEnabled,
        public ?string $lastLoginAt,
        public string $createdAt,
        /** The address waiting on its confirmation link, if one was requested. */
        public ?string $pendingEmail = null,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            locale: $user->locale,
            avatarUrl: AvatarStorage::url($user),
            role: $user->role?->key,
            isRoot: $user->is_root,
            emailVerified: $user->email_verified_at !== null,
            twoFactorEnabled: $user->hasEnabledTwoFactor(),
            lastLoginAt: $user->last_login_at?->toIso8601String(),
            createdAt: $user->created_at?->toIso8601String() ?? '',
            pendingEmail: $user->relationLoaded('emailChange') && $user->emailChange?->isPending()
                ? $user->emailChange->new_email
                : null,
        );
    }
}
