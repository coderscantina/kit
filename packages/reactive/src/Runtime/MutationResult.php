<?php

declare(strict_types=1);

namespace Kit\Reactive\Runtime;

final class MutationResult
{
    public function __construct(
        public readonly mixed $result,
        public readonly int $mutationId,
    ) {}
}
