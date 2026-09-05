<?php

declare(strict_types=1);

namespace Tests\Fixtures\Reactive;

use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Attributes\ReactiveQuery;
use Kit\Reactive\NoArgs;
use Kit\Reactive\Query;
use Spatie\LaravelData\Data;

/**
 * Table-level tracking only, no declared predicate.
 *
 * @extends Query<NoArgs>
 */
#[ReactiveQuery('notes.all', result: NoteData::class, list: true)]
final class AllNotes extends Query
{
    public static function args(): string
    {
        return NoArgs::class;
    }

    public function authorize(Authenticatable $user, Data $args): void
    {
        $this->gate($user)->authorize('view-all-notes');
    }

    public function handle(Data $args): mixed
    {
        return NoteData::collect(Note::query()->orderBy('id')->get());
    }
}
