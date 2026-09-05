<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Role;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class RoleData extends Data
{
    /**
     * @param  array<int, string>  $abilities
     */
    public function __construct(
        public string $key,
        public string $name,
        public int $level,
        public array $abilities,
    ) {}

    public static function fromModel(Role $role): self
    {
        return new self(key: $role->key, name: $role->name, level: $role->level, abilities: $role->abilities);
    }
}
