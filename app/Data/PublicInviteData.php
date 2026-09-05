<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Invite;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the token-gated accept page may see. No inviter id, no internal state.
 */
#[TypeScript]
class PublicInviteData extends Data
{
    public function __construct(
        public string $id,
        public string $email,
        public string $role,
        public ?string $invitedBy,
        public string $expiresAt,
        public bool $accountExists,
    ) {}

    public static function fromModel(Invite $invite, bool $accountExists): self
    {
        return new self(
            id: $invite->id,
            email: $invite->email,
            role: $invite->role->key,
            invitedBy: $invite->inviter?->name,
            expiresAt: $invite->expires_at->toIso8601String(),
            accountExists: $accountExists,
        );
    }
}
