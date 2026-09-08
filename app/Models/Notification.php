<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Kit\Reactive\Invalidation\HasReactiveInvalidation;

/**
 * The inbox row.
 *
 * Laravel's own notification model with three things added: the escalation
 * ladder it was sent with, what each channel actually did, and an archive
 * timestamp. It carries HasReactiveInvalidation, which is why the app can
 * subscribe to an inbox at all: without it a delivery would write a row that
 * no subscription ever hears about.
 *
 * `read_at` is the framework's column and the app reads it as "seen". That is
 * also the escalation signal: a notification the person has laid eyes on
 * stops climbing the ladder.
 *
 * @property string $id
 * @property string $type
 * @property string $notifiable_type
 * @property string $notifiable_id
 * @property array<string, mixed> $data
 * @property array<int, string> $channels
 * @property array<string, string> $deliveries
 * @property Carbon|null $read_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Notification extends DatabaseNotification
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    use HasReactiveInvalidation;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'channels' => '[]',
        'deliveries' => '{}',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'channels' => 'array',
            'deliveries' => 'array',
            'read_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function status(): NotificationStatus
    {
        return match (true) {
            $this->archived_at !== null => NotificationStatus::Archived,
            $this->read_at !== null => NotificationStatus::Seen,
            default => NotificationStatus::Unseen,
        };
    }

    /**
     * The rungs still ahead of the one that was last delivered. Read by the
     * escalation guard, so a channel that already went out is never retried.
     *
     * @return array<int, NotificationChannel>
     */
    public function pendingChannels(): array
    {
        $done = array_keys($this->deliveries);

        return array_values(array_filter(
            NotificationChannel::fromValues($this->channels),
            fn (NotificationChannel $channel): bool => ! in_array($channel->value, $done, true),
        ));
    }

    /** Stamp a channel as delivered, keeping whatever is already there. */
    public function recordDelivery(string $channel): void
    {
        $this->deliveries = [...$this->deliveries, $channel => now()->toIso8601String()];
        $this->save();
    }

    /**
     * @param  Builder<Notification>  $query
     * @return Builder<Notification>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * @param  Builder<Notification>  $query
     * @return Builder<Notification>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}
