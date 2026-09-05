<?php

declare(strict_types=1);

namespace Kit\Reactive\Attributes;

use Attribute;
use Spatie\LaravelData\Data;

/**
 * @param  class-string<Data>|null  $result
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ReactiveMutation
{
    public function __construct(
        public string $name,
        public ?string $result = null,
        public bool $list = false,
    ) {}
}
