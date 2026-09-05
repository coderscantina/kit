<?php

declare(strict_types=1);

namespace Tests\Fixtures\Reactive;

use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Attributes\ReactiveQuery;
use Kit\Reactive\Dep;
use Kit\Reactive\Query;

#[ReactiveQuery('notes.list', result: NoteData::class, list: true)]
final class ListNotes extends Query
{
    public function rules(): array
    {
        return ['ownerId' => ['required', 'string']];
    }

    public function authorize(Authenticatable $user, array $args): void
    {
        $this->gate($user)->authorize('view-notes', [$args['ownerId']]);
    }

    public function reads(array $args): array
    {
        return [Dep::eq('notes', 'owner_id', $args['ownerId'])];
    }

    public function handle(array $args): mixed
    {
        return NoteData::collect(Note::query()->where('owner_id', $args['ownerId'])->orderBy('id')->get());
    }
}
