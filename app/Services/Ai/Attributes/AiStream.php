<?php

declare(strict_types=1);

namespace App\Services\Ai\Attributes;

use App\Services\Ai\AiAction;
use Attribute;

/**
 * Registers an {@see AiAction} under a client-facing name, the same
 * `feature.verb` shape the reactive layer uses. The name is what
 * `useAiStream()` asks for and what the generated `Kit.AiMap` types.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AiStream
{
    public function __construct(
        public string $name,
    ) {}
}
