<?php

declare(strict_types=1);

namespace App\Data;

use Laravel\Sanctum\PersonalAccessToken;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A personal access token as its owner sees it. The secret is never in here. */
#[TypeScript]
final class AccessTokenData extends Data
{
    /**
     * @param  array<int, string>  $abilities
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $abilities,
        public ?string $lastUsedAt,
        public ?string $expiresAt,
        public string $createdAt,
    ) {}

    public static function fromModel(PersonalAccessToken $token): self
    {
        return new self(
            id: (string) $token->id,
            name: $token->name,
            abilities: array_values($token->abilities ?? []),
            lastUsedAt: $token->last_used_at?->toIso8601String(),
            expiresAt: $token->expires_at?->toIso8601String(),
            createdAt: $token->created_at?->toIso8601String() ?? '',
        );
    }
}
