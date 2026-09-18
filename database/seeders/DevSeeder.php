<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One verified account per role, for local development only:
 * `<role>@kit.test` with the password `password`, no two-factor. Re-running
 * puts the role, verification and 2FA back but keeps a changed password.
 * `GET /dev/login/{role}` signs in as these.
 */
class DevSeeder extends Seeder
{
    public const string PASSWORD = 'password';

    public static function email(string $role): string
    {
        return "{$role}@kit.test";
    }

    public function run(): void
    {
        foreach (Role::query()->get() as $role) {
            $user = User::query()->firstOrNew(['email' => self::email($role->key)]);

            if (! $user->exists) {
                $user->password = self::PASSWORD;
            }

            $user->name = $role->name;
            $user->role_id = $role->id;
            $user->is_root = false;
            $user->email_verified_at ??= now();
            $user->two_factor_secret = null;
            $user->two_factor_backup_codes = null;
            $user->two_factor_confirmed_at = null;
            $user->save();
        }
    }
}
