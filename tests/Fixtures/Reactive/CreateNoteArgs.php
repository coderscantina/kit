<?php

declare(strict_types=1);

namespace Tests\Fixtures\Reactive;

use App\Rules\BoundedString;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

final class CreateNoteArgs extends Data
{
    public function __construct(
        #[Rule(new BoundedString(1, 100))]
        public string $title,
        #[Max(20000)]
        public string $body = '',
    ) {}
}
