<?php

declare(strict_types=1);

namespace Tests\Fixtures\Reactive;

use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Attributes\ReactiveMutation;
use Kit\Reactive\Mutation;
use Spatie\LaravelData\Data;

/**
 * The versioned write: locks the row against the version the client read.
 *
 * @extends Mutation<EditNoteArgs>
 */
#[ReactiveMutation('notes.edit', result: NoteData::class)]
final class EditNote extends Mutation
{
    public static function args(): string
    {
        return EditNoteArgs::class;
    }

    public function authorize(Authenticatable $user, Data $args): void
    {
        $this->gate($user)->authorize('create-notes');
    }

    public function handle(Data $args): NoteData
    {
        $note = $this->lockVersion(Note::query()->findOrFail($args->id), $args->version);

        $note->fill(['title' => $args->title, 'body' => $args->body])->save();

        return NoteData::fromModel($note);
    }
}
