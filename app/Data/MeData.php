<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The authenticated user plus everything the SPA needs to decide what to
 * show: the resolved ability list and whether this session is impersonated.
 */
#[TypeScript]
class MeData extends Data
{
    /**
     * @param  array<int, string>  $abilities
     */
    public function __construct(
        public UserData $user,
        public array $abilities,
        public bool $impersonating,
    ) {}

    public static function fromUser(User $user, bool $impersonating): self
    {
        return new self(
            user: UserData::fromModel($user),
            abilities: app(AuthorizationService::class)->abilitiesFor($user),
            impersonating: $impersonating,
        );
    }
}
