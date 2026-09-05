<?php

declare(strict_types=1);

namespace Kit\Reactive\Push;

use Kit\Reactive\Contracts\Pusher;
use Kit\Reactive\Events\ResultChanged;
use Kit\Reactive\Events\SubscriptionRevoked;
use Kit\Reactive\Registry\Subscription;

final class BroadcastPusher implements Pusher
{
    public function push(Subscription $subscription, int $mutationId, string $hash, mixed $result): void
    {
        broadcast(new ResultChanged($subscription->id, $mutationId, $hash, $result));
    }

    public function revoke(Subscription $subscription): void
    {
        broadcast(new SubscriptionRevoked($subscription->id));
    }
}
