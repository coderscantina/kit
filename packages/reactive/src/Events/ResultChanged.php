<?php

declare(strict_types=1);

namespace Kit\Reactive\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Kit\Reactive\Runtime\Canonical;

/**
 * The push (§4.6). Reverb drops frames over 10 KB on the client side
 * without an error, so the result rides inline only when the serialized
 * form fits under the threshold; otherwise the client fetches it through
 * /rq/query and reconciles against the same mutationId.
 *
 * Broadcast synchronously: the worker is already a queue job, and a second
 * hop would add latency to the 200 ms budget for nothing.
 */
final class ResultChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    private readonly string $encoded;

    public function __construct(
        public readonly string $subscriptionId,
        public readonly int $mutationId,
        public readonly string $hash,
        mixed $result,
    ) {
        $this->encoded = Canonical::encode($result);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("subscription.{$this->subscriptionId}");
    }

    public function broadcastAs(): string
    {
        return 'ResultChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $payload = [
            'subscriptionId' => $this->subscriptionId,
            'mutationId' => $this->mutationId,
            'hash' => $this->hash,
        ];

        if (strlen($this->encoded) <= (int) config('reactive.inline_result_bytes', 8192)) {
            $payload['result'] = json_decode($this->encoded, true, 512, JSON_THROW_ON_ERROR);
        }

        return $payload;
    }

    public function resultInline(): bool
    {
        return array_key_exists('result', $this->broadcastWith());
    }
}
