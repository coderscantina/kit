<?php

declare(strict_types=1);

namespace App\Support;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class EchoConfigPayload extends Data
{
    public function __construct(
        public string $key,
        public string $wsHost,
        public int $wsPort,
        public int $wssPort,
        public bool $forceTLS,
    ) {}
}
