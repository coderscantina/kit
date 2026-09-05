<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Invite;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class InviteData extends Data
{
    public function __construct(
        public string $id,
        public string $email,
        public string $role,
        public ?string $invitedBy,
        public string $expiresAt,
        public string $status,
        public string $createdAt,
    ) {}

    public static function fromModel(Invite $invite): self
    {
        return new self(
            id: $invite->id,
            email: $invite->email,
            role: $invite->role->key,
            invitedBy: $invite->inviter?->name,
            expiresAt: $invite->expires_at->toIso8601String(),
            status: match (true) {
                $invite->accepted_at !== null => 'accepted',
                $invite->declined_at !== null => 'declined',
                $invite->expires_at->isPast() => 'expired',
                default => 'pending',
            },
            createdAt: $invite->created_at?->toIso8601String() ?? '',
        );
    }
}
