<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Ulid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One endpoint, by id. */
#[TypeScript]
final class WebhookArgs extends Data
{
    public function __construct(
        #[Ulid]
        public string $id,
    ) {}
}
