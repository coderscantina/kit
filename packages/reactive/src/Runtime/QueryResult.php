<?php

declare(strict_types=1);

namespace Kit\Reactive\Runtime;

use Kit\Reactive\Dep;
use Spatie\LaravelData\Data;

final class QueryResult
{
    /**
     * @param  array<int, string>  $tables
     * @param  array<int, Dep>  $deps
     */
    public function __construct(
        public readonly mixed $result,
        public readonly string $hash,
        public readonly Data $args,
        public readonly array $tables,
        public readonly array $deps,
    ) {}
}
