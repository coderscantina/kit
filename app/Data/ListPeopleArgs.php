<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Ulid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What `people.list` takes.
 *
 * `viewerId` is an argument for the same reason `ListNotificationsArgs::$userId`
 * is: handle() never sees the caller, so who is looking travels with the
 * question, and authorize() refuses any id but the caller's.
 *
 * `params` is the query bag `useTableQueryState` produces (`page`, `per_page`,
 * `sort`, `q` and the filter chips). PeopleFilter's allow list decides what
 * reaches SQL, so a new filter still needs no edit here.
 */
#[TypeScript]
final class ListPeopleArgs extends Data
{
    /**
     * @param  array<string, string|int>|null  $params
     */
    public function __construct(
        #[Ulid]
        public string $viewerId,
        public ?array $params = null,
    ) {}
}
