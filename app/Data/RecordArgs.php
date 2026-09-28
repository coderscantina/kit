<?php

declare(strict_types=1);

namespace App\Data;

use App\Support\Records;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Ulid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One record, by table and id: `{ type: 'posts', id }`. The history and the
 * attachments of a record are both asked for this way.
 */
#[TypeScript]
final class RecordArgs extends Data
{
    public function __construct(
        #[Regex(Records::TYPE_PATTERN)]
        public string $type,
        #[Ulid]
        public string $id,
    ) {}
}
