<?php

declare(strict_types=1);

namespace Kit\Reactive\Runtime;

use Kit\Reactive\Registry\Computation;
use Kit\Reactive\Registry\Subscription;

final class SubscribeResult
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly Computation $computation,
    ) {}
}
