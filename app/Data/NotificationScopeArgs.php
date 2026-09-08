<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Ulid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Which of an account's notifications a mutation applies to.
 *
 * Null `ids` means "all of them in the state this mutation acts on" — mark
 * every unseen one seen, archive every seen one. That is one round trip for
 * the button every inbox has, instead of a list of ids that is stale by the
 * time it arrives.
 *
 * The cap is there so an explicit list stays a statement rather than a table
 * scan someone typed by hand.
 */
#[TypeScript]
final class NotificationScopeArgs extends Data
{
    /**
     * @param  array<int, string>|null  $ids
     */
    public function __construct(
        #[Ulid]
        public string $userId,
        #[ArrayType, Max(200)]
        public ?array $ids = null,
    ) {}
}
