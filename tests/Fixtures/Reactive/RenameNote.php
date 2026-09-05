<?php

declare(strict_types=1);

namespace Tests\Fixtures\Reactive;

use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Attributes\ReactiveMutation;
use Kit\Reactive\Mutation;
use Spatie\LaravelData\Data;

/**
 * @extends Mutation<RenameNoteArgs>
 */
#[ReactiveMutation('notes.rename', result: NoteData::class)]
final class RenameNote extends Mutation
{
    public static function args(): string
    {
        return RenameNoteArgs::class;
    }

    public function authorize(Authenticatable $user, Data $args): void
    {
        $this->gate($user)->authorize('create-notes');
    }

    public function handle(Data $args): NoteData
    {
        $note = $this->lock(Note::query()->findOrFail($args->id));
        $note->title = $args->title;
        $note->save();

        return NoteData::fromModel($note);
    }
}
