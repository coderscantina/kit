<?php

declare(strict_types=1);

namespace Kit\Reactive\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

final class SubscriptionRevoked implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly string $subscriptionId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("subscription.{$this->subscriptionId}");
    }

    public function broadcastAs(): string
    {
        return 'SubscriptionRevoked';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return ['subscriptionId' => $this->subscriptionId];
    }
}
