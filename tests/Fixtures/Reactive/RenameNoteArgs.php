<?php

declare(strict_types=1);

namespace Tests\Fixtures\Reactive;

use App\Rules\BoundedString;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Ulid;
use Spatie\LaravelData\Data;

final class RenameNoteArgs extends Data
{
    public function __construct(
        #[Ulid]
        public string $id,
        #[Rule(new BoundedString(1, 100))]
        public string $title,
    ) {}
}
