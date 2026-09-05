<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Models\Role;
use App\Services\Auth\AuthorizationService;

/**
 * Mirrors config/abilities.php into the roles table. Abilities a role lists
 * but the registry does not know are dropped, so a typo cannot grant access.
 */
class SyncRoles
{
    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {}

    /**
     * @return array<int, string> keys of the roles that were written
     */
    public function execute(): array
    {
        /** @var array<int, string> $known */
        $known = config('abilities.abilities', []);
        /** @var array<string, array{name: string, level: int, abilities: array<int, string>}> $roles */
        $roles = config('abilities.roles', []);

        $written = [];

        foreach ($roles as $key => $definition) {
            $abilities = array_values(array_intersect($definition['abilities'], $known));
            sort($abilities);

            $role = Role::query()->updateOrCreate(
                ['key' => $key],
                [
                    'name' => $definition['name'],
                    'level' => $definition['level'],
                    'abilities' => $abilities,
                ],
            );

            $this->authorization->invalidateRole($role);
            $written[] = $key;
        }

        return $written;
    }
}
