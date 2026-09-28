<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** The events an endpoint can listen for: each audited record type with the changes it can have. */
#[TypeScript]
final class WebhookEventsData extends Data
{
    /**
     * @param  array<int, string>  $events
     */
    public function __construct(
        public array $events,
    ) {}
}
