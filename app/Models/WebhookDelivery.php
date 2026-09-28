<?php

declare(strict_types=1);

namespace App\Models;

use App\Jobs\DeliverWebhook;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One event on its way to one endpoint. A pending row is the queue entry:
 * creating it queues the delivery once the surrounding transaction commits,
 * so a rolled-back write never sends anything, and every way of producing
 * an event (a record change, a test ping) goes through the same door.
 *
 * @property string $id
 * @property string $webhook_endpoint_id
 * @property string $event
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $attempts
 * @property int|null $response_status
 * @property string|null $response_body
 * @property Carbon|null $delivered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WebhookEndpoint $endpoint
 */
#[Fillable(['webhook_endpoint_id', 'event', 'payload', 'status', 'attempts', 'response_status', 'response_body', 'delivered_at'])]
class WebhookDelivery extends Model
{
    use MassPrunable;

    public const string PENDING = 'pending';

    public const string SUCCEEDED = 'succeeded';

    public const string FAILED = 'failed';

    protected static function booted(): void
    {
        static::created(function (self $delivery): void {
            DeliverWebhook::dispatch($delivery->id)->afterCommit();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'response_status' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) config('kit.webhooks.retention_days', 30)));
    }
}
