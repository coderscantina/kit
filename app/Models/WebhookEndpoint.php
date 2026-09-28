<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A URL that receives record changes, signed the Standard Webhooks way
 * (https://www.standardwebhooks.com): `webhook-id`, `webhook-timestamp` and
 * `webhook-signature: v1,<base64 HMAC-SHA256 of "id.timestamp.body">`, keyed
 * with the base64 part of the `whsec_` secret. Receivers can verify with any
 * Standard Webhooks library.
 *
 * @property string $id
 * @property string $url
 * @property string|null $description
 * @property string $secret
 * @property array<int, string> $events
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['url', 'description', 'secret', 'events', 'active'])]
#[Hidden(['secret'])]
class WebhookEndpoint extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'active' => 'boolean',
        ];
    }

    public static function generateSecret(): string
    {
        return 'whsec_'.base64_encode(random_bytes(32));
    }

    /** `posts.updated` matches `posts.updated`, `posts.*` and `*`. */
    public function listensTo(string $event): bool
    {
        foreach ($this->events as $pattern) {
            if ($pattern === '*' || $pattern === $event || (str_ends_with($pattern, '.*') && str_starts_with($event, substr($pattern, 0, -1)))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{webhook-id: string, webhook-timestamp: string, webhook-signature: string}
     */
    public function signatureHeaders(string $id, int $timestamp, string $body): array
    {
        $key = base64_decode(Str::after($this->secret, 'whsec_'), true);
        $signature = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", (string) $key, true));

        return [
            'webhook-id' => $id,
            'webhook-timestamp' => (string) $timestamp,
            'webhook-signature' => 'v1,'.$signature,
        ];
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * @return HasOne<WebhookDelivery, $this>
     */
    public function latestDelivery(): HasOne
    {
        return $this->hasOne(WebhookDelivery::class)->latestOfMany('id');
    }
}
