<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Answers "can this user do X" from a per-user ability list cached for an
 * hour and busted on role change. `is_root` short-circuits to every ability.
 *
 * Scope is deliberately absent from the signature. When a tenant boundary
 * arrives, can() gains a scope argument and the ability strings stay.
 */
class AuthorizationService
{
    public function can(User $user, string $ability): bool
    {
        return $user->is_root || in_array($ability, $this->abilitiesFor($user), true);
    }

    /**
     * @return array<int, string>
     */
    public function abilitiesFor(User $user): array
    {
        if ($user->is_root) {
            /** @var array<int, string> $all */
            $all = config('abilities.abilities', []);

            return $all;
        }

        /** @var array<int, string> $abilities */
        $abilities = Cache::remember(
            $this->cacheKey($user->id),
            now()->addSeconds((int) config('abilities.cache_ttl_seconds', 3600)),
            fn () => $this->load($user),
        );

        return $abilities;
    }

    public function invalidateUser(User|string $user): void
    {
        Cache::forget($this->cacheKey($user instanceof User ? $user->id : $user));
    }

    public function invalidateRole(Role $role): void
    {
        DB::table('users')
            ->where('role_id', $role->id)
            ->pluck('id')
            ->each(fn (string $id) => $this->invalidateUser($id));
    }

    /**
     * @return array<int, string>
     */
    private function load(User $user): array
    {
        if ($user->role_id === null) {
            return [];
        }

        $abilities = DB::table('roles')->where('id', $user->role_id)->value('abilities');

        if (! is_string($abilities)) {
            return [];
        }

        /** @var array<int, string> $decoded */
        $decoded = json_decode($abilities, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function cacheKey(string $userId): string
    {
        return "authz:user:{$userId}:v1";
    }
}
