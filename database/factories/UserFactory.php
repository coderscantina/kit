<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= 'password',
            'locale' => 'en',
            'phone' => null,
            'phone_verified_at' => null,
            'avatar_path' => null,
            // Every column, not just the required ones: models are strict about
            // missing attributes and a created (unrefreshed) instance has only
            // what the factory gave it, not the database defaults.
            'role_id' => null,
            'is_root' => false,
            'two_factor_secret' => null,
            'two_factor_backup_codes' => null,
            'two_factor_confirmed_at' => null,
            'last_login_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => ['email_verified_at' => null]);
    }

    /** An account the SMS channel can actually reach. */
    public function withVerifiedPhone(string $phone = '+15551234567'): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => $phone,
            'phone_verified_at' => now(),
        ]);
    }

    public function root(): static
    {
        return $this->state(fn (array $attributes) => ['is_root' => true]);
    }
}
