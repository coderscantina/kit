<?php

declare(strict_types=1);

namespace Tests\Fixtures\Reactive;

use App\Rules\BoundedString;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Ulid;
use Spatie\LaravelData\Data;

/** A whole-row edit that states the version it was written against. */
final class EditNoteArgs extends Data
{
    public function __construct(
        #[Ulid]
        public string $id,
        #[Min(1)]
        public int $version,
        #[Rule(new BoundedString(1, 100))]
        public string $title,
        #[Rule(new BoundedString(0, 4000))]
        public string $body = '',
    ) {}
}
