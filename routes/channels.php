<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Presence;
use Illuminate\Support\Facades\Broadcast;

// Reactive subscription channels are registered by the reactive package.
// Feature channels go here; private only when they carry resource data.

// The roster of a screen. The resource segment names the ability that opens
// it (App\Support\Presence), so a new roster needs no registration here.
// Returning null rather than false: both reject, and null reads as "no
// member payload", which is what a presence callback is being asked for.
Broadcast::channel('presence.{resource}', fn (User $user, string $resource): ?array => Presence::join($user, $resource));
