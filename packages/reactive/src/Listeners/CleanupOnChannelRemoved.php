<?php

declare(strict_types=1);

namespace Kit\Reactive\Listeners;

use Kit\Reactive\Contracts\Registry;
use Laravel\Reverb\Events\ChannelRemoved;

/**
 * Reverb fires ChannelRemoved when the last connection leaves a channel,
 * whether by an explicit leave, a closed tab, or the stale-connection
 * pruner (which unsubscribes the connection from every channel first). The
 * subscription is gone with it. Runs inside the Reverb process.
 */
final class CleanupOnChannelRemoved
{
    public function __construct(
        private readonly Registry $registry,
    ) {}

    public function handle(ChannelRemoved $event): void
    {
        $name = $event->channel->name();

        if (! str_starts_with($name, 'private-subscription.')) {
            return;
        }

        $this->registry->delete(substr($name, strlen('private-subscription.')));
    }
}
