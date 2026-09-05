<?php

declare(strict_types=1);

namespace App\Support;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SocialProviderPayload extends Data
{
    public function __construct(
        public string $key,
        public string $url,
    ) {}
}
