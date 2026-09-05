<?php

declare(strict_types=1);

namespace Tests\Fixtures\Reactive;

use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Attributes\ReactiveQuery;
use Kit\Reactive\Query;

/** Table-level tracking only, no declared predicate. */
#[ReactiveQuery('notes.all', result: NoteData::class, list: true)]
final class AllNotes extends Query
{
    public function rules(): array
    {
        return [];
    }

    public function authorize(Authenticatable $user, array $args): void
    {
        $this->gate($user)->authorize('view-all-notes');
    }

    public function handle(array $args): mixed
    {
        return NoteData::collect(Note::query()->orderBy('id')->get());
    }
}
