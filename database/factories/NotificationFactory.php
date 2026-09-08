<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Found by name: Laravel resolves App\Models\Notification to this class.
 *
 * Pins its own id and leaves the owner to the caller: an inbox row without a
 * person is meaningless, and defaulting to a fresh user hides the case where
 * a test meant to reuse one.
 *
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => 'security.alert',
            'notifiable_type' => (new User)->getMorphClass(),
            'notifiable_id' => User::factory(),
            'data' => ['event' => 'password_changed'],
            'channels' => ['push', 'mail'],
            'deliveries' => ['database' => now()->toIso8601String()],
            'read_at' => null,
            'archived_at' => null,
        ];
    }

    public function seen(): static
    {
        return $this->state(fn (array $attributes) => ['read_at' => now()]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now(),
            'archived_at' => now(),
        ]);
    }
}
