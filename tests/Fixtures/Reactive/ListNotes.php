<?php

declare(strict_types=1);

namespace Tests\Fixtures\Reactive;

use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Attributes\ReactiveQuery;
use Kit\Reactive\Dep;
use Kit\Reactive\Query;
use Spatie\LaravelData\Data;

/**
 * @extends Query<ListNotesArgs>
 */
#[ReactiveQuery('notes.list', result: NoteData::class, list: true)]
final class ListNotes extends Query
{
    public static function args(): string
    {
        return ListNotesArgs::class;
    }

    public function authorize(Authenticatable $user, Data $args): void
    {
        $this->gate($user)->authorize('view-notes', [$args->ownerId]);
    }

    public function reads(Data $args): array
    {
        return [Dep::eq('notes', 'owner_id', $args->ownerId)];
    }

    public function handle(Data $args): mixed
    {
        return NoteData::collect(Note::query()->where('owner_id', $args->ownerId)->orderBy('id')->get());
    }
}
