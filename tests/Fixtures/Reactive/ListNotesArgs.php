<?php

declare(strict_types=1);

namespace Tests\Fixtures\Reactive;

use Spatie\LaravelData\Data;

final class ListNotesArgs extends Data
{
    public function __construct(
        public string $ownerId,
    ) {}
}
