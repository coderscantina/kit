<?php

declare(strict_types=1);

namespace App\Support;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One sign-in button, described by the server. The client renders whatever
 * arrives, so adding a provider is a config change and not a frontend one.
 */
#[TypeScript]
class SocialProviderPayload extends Data
{
    public function __construct(
        public string $key,
        public string $label,
        /** Iconify name, resolved by the client's Icon component. */
        public string $icon,
    ) {}
}
