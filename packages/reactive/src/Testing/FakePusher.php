<?php

declare(strict_types=1);

namespace Kit\Reactive\Testing;

use Kit\Reactive\Contracts\Pusher;
use Kit\Reactive\Registry\Subscription;

final class FakePusher implements Pusher
{
    /** @var array<int, array{subscription: Subscription, mutationId: int, hash: string, result: mixed}> */
    public array $pushes = [];

    /** @var array<int, Subscription> */
    public array $revoked = [];

    public function push(Subscription $subscription, int $mutationId, string $hash, mixed $result): void
    {
        $this->pushes[] = ['subscription' => $subscription, 'mutationId' => $mutationId, 'hash' => $hash, 'result' => $result];
    }

    public function revoke(Subscription $subscription): void
    {
        $this->revoked[] = $subscription;
    }
}
