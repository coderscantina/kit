<?php

declare(strict_types=1);

namespace Tests\Fixtures\Reactive;

use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Attributes\ReactiveMutation;
use Kit\Reactive\Mutation;
use RuntimeException;

/** Writes a row, then throws: the row must roll back and nothing may push. */
#[ReactiveMutation('notes.fail')]
final class FailingMutation extends Mutation
{
    public function rules(): array
    {
        return [];
    }

    public function authorize(Authenticatable $user, array $args): void
    {
        $this->gate($user)->authorize('create-notes');
    }

    public function handle(array $args): mixed
    {
        Note::query()->create(['owner_id' => (string) auth()->id(), 'title' => 'doomed']);

        throw new RuntimeException('boom');
    }
}
