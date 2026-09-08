<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A column of the preferences matrix.
 *
 * `deliverable` is not the same as enabled: a user can switch push on before
 * allowing the browser prompt, or SMS on before verifying a number. The
 * screen shows the switch either way and says what is still missing, because
 * hiding it would leave no way to discover the feature.
 */
#[TypeScript]
final class NotificationChannelData extends Data
{
    public function __construct(
        public string $key,
        /** Configured for this installation at all. */
        public bool $available,
        /** Reachable for this account right now. */
        public bool $deliverable,
    ) {}
}
