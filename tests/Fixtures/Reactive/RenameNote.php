<?php

declare(strict_types=1);

namespace Tests\Fixtures\Reactive;

use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Attributes\ReactiveMutation;
use Kit\Reactive\Mutation;

#[ReactiveMutation('notes.rename', result: NoteData::class)]
final class RenameNote extends Mutation
{
    public function rules(): array
    {
        return ['id' => ['required', 'ulid'], 'title' => ['required', 'string', 'max:100']];
    }

    public function authorize(Authenticatable $user, array $args): void
    {
        $this->gate($user)->authorize('create-notes');
    }

    public function handle(array $args): NoteData
    {
        $note = $this->lock(Note::query()->findOrFail($args['id']));
        $note->title = $args['title'];
        $note->save();

        return NoteData::fromModel($note);
    }
}
