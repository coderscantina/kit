<?php

declare(strict_types=1);

namespace Kit\Reactive\Runtime;

use Kit\Reactive\Dep;

final class QueryResult
{
    /**
     * @param  array<string, mixed>  $args
     * @param  array<int, string>  $tables
     * @param  array<int, Dep>  $deps
     */
    public function __construct(
        public readonly mixed $result,
        public readonly string $hash,
        public readonly array $args,
        public readonly array $tables,
        public readonly array $deps,
    ) {}
}
