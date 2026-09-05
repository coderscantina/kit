<?php

declare(strict_types=1);

namespace Kit\Reactive\Contracts;

use Kit\Reactive\Registry\Subscription;

interface Pusher
{
    public function push(Subscription $subscription, int $mutationId, string $hash, mixed $result): void;

    public function revoke(Subscription $subscription): void;
}
