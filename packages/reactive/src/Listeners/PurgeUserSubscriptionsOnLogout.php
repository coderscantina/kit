<?php

declare(strict_types=1);

namespace Kit\Reactive\Listeners;

use Illuminate\Auth\Events\Logout;
use Kit\Reactive\Contracts\Registry;

final class PurgeUserSubscriptionsOnLogout
{
    public function __construct(
        private readonly Registry $registry,
    ) {}

    public function handle(Logout $event): void
    {
        if ($event->user === null) {
            return;
        }

        $this->registry->purgeUser((string) $event->user->getAuthIdentifier());
    }
}
