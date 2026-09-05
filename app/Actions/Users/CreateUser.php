<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\Role;
use App\Models\User;
use App\Support\InstallState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates an account. The very first account becomes the owner; later
 * accounts get the role they were invited with, or none at all.
 */
class CreateUser
{
    public function __construct(
        private readonly InstallState $state,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string, locale?: string, email_verified_at?: Carbon|null}  $attributes
     */
    public function execute(array $attributes, ?Role $role = null): User
    {
        $user = DB::transaction(function () use ($attributes, $role) {
            // Serialise first-account detection: two concurrent registrations
            // on a fresh install must not both become owner.
            $isFirst = ! User::query()->lockForUpdate()->exists();

            $user = new User;
            $user->fill([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
                'locale' => $attributes['locale'] ?? app()->getLocale(),
                'role_id' => $isFirst ? Role::byKey(Role::OWNER)->id : $role?->id,
            ]);
            $user->email_verified_at = $attributes['email_verified_at'] ?? null;
            $user->save();

            return $user;
        });

        $this->state->closeRegistration();

        return $user;
    }
}
