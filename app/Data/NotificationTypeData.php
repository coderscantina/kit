<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the preferences matrix: a notification type, the ladder it
 * supports, and where this account currently has it switched on.
 *
 * `channels` is ordered, and the order is the escalation order — which is
 * what lets the screen say "push first, then email" without the client
 * knowing anything about the ladder.
 */
#[TypeScript]
final class NotificationTypeData extends Data
{
    /**
     * @param  array<int, string>  $channels  the ladder, in escalation order
     * @param  array<int, string>  $required  channels the user cannot switch off
     * @param  array<int, string>  $enabled  the current choice
     */
    public function __construct(
        public string $key,
        public string $group,
        public array $channels,
        public array $required,
        public array $enabled,
    ) {}
}
