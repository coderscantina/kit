<?php

declare(strict_types=1);

namespace Kit\Reactive\Attributes;

use Attribute;
use Spatie\LaravelData\Data;

/**
 * Registers a Query under a client-facing name. `result` and `list` feed the
 * TypeScript generator when the return type of handle() is not a single
 * Data class (a collection, an array).
 *
 * @param  class-string<Data>|null  $result
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ReactiveQuery
{
    public function __construct(
        public string $name,
        public ?string $result = null,
        public bool $list = false,
    ) {}
}
