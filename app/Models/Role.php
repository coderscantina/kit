<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named ability set. Rows mirror config/abilities.php and are synced by
 * kit:setup; the table exists so a user's role is a foreign key rather than a
 * string that might drift from the config.
 *
 * @property string $id
 * @property string $key
 * @property string $name
 * @property int $level
 * @property array<int, string> $abilities
 */
#[Fillable(['key', 'name', 'level', 'abilities'])]
class Role extends Model
{
    public const string OWNER = 'owner';

    public const string ADMIN = 'admin';

    public const string MEMBER = 'member';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'level' => 'integer',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public static function byKey(string $key): self
    {
        return self::query()->where('key', $key)->firstOrFail();
    }
}
