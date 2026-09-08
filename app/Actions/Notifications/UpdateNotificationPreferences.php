<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Enums\NotificationChannel;
use App\Models\User;
use App\Support\Notifications\ChannelResolver;
use App\Support\Notifications\NotificationRegistry;
use Illuminate\Support\Facades\DB;

/**
 * Writes an account's channel choices.
 *
 * A row is written for every type in the payload, including the ones with
 * nothing switched on: a missing row means "has not chosen" and takes the
 * type's defaults, so silence and a deliberate "inbox only" have to be
 * different things on disk.
 *
 * Unknown types and channels are dropped rather than rejected. A client that
 * still has last release's list in its bundle should save the switches that
 * do exist, not fail the whole screen.
 */
class UpdateNotificationPreferences
{
    public function __construct(
        private readonly NotificationRegistry $registry,
        private readonly ChannelResolver $resolver,
    ) {}

    /**
     * @param  array<string, array<int, string>>  $preferences  type key => channels
     */
    public function execute(User $user, array $preferences): void
    {
        DB::transaction(function () use ($user, $preferences): void {
            foreach ($preferences as $type => $channels) {
                $definition = $this->registry->find((string) $type);

                if ($definition === null) {
                    continue;
                }

                $chosen = array_values(array_filter(
                    NotificationChannel::fromValues($channels),
                    // A rung the type does not offer is not a choice, and a
                    // required rung is stored whether it was sent or not so
                    // the row reads the same as what will be delivered.
                    fn (NotificationChannel $channel): bool => $definition->offers($channel),
                ));

                foreach ($definition->required as $required) {
                    if (! in_array($required, $chosen, true)) {
                        $chosen[] = $required;
                    }
                }

                $user->notificationPreferences()->updateOrCreate(
                    ['type' => $definition->key],
                    ['channels' => NotificationChannel::toValues($chosen)],
                );
            }
        });

        $this->resolver->forget($user);
    }
}
