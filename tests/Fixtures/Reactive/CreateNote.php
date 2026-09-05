<?php

declare(strict_types=1);

namespace Tests\Fixtures\Reactive;

use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Attributes\ReactiveMutation;
use Kit\Reactive\Mutation;
use Spatie\LaravelData\Data;

/**
 * @extends Mutation<CreateNoteArgs>
 */
#[ReactiveMutation('notes.create', result: NoteData::class)]
final class CreateNote extends Mutation
{
    public static function args(): string
    {
        return CreateNoteArgs::class;
    }

    public function authorize(Authenticatable $user, Data $args): void
    {
        $this->gate($user)->authorize('create-notes');
    }

    public function handle(Data $args): NoteData
    {
        $note = Note::query()->create([
            'owner_id' => (string) auth()->id(),
            'title' => $args->title,
            'body' => $args->body,
        ]);

        return NoteData::fromModel($note);
    }
}
