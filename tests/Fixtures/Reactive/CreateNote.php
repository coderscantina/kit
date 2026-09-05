<?php

declare(strict_types=1);

namespace Tests\Fixtures\Reactive;

use App\Rules\BoundedString;
use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Attributes\ReactiveMutation;
use Kit\Reactive\Mutation;

#[ReactiveMutation('notes.create', result: NoteData::class)]
final class CreateNote extends Mutation
{
    public function rules(): array
    {
        return ['title' => [new BoundedString(1, 100)], 'body' => ['sometimes', 'string', 'max:20000']];
    }

    public function authorize(Authenticatable $user, array $args): void
    {
        $this->gate($user)->authorize('create-notes');
    }

    public function handle(array $args): NoteData
    {
        $note = Note::query()->create([
            'owner_id' => (string) auth()->id(),
            'title' => $args['title'],
            'body' => $args['body'] ?? '',
        ]);

        return NoteData::fromModel($note);
    }
}
