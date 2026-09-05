<?php

declare(strict_types=1);

namespace Tests\Fixtures\Reactive;

use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Attributes\ReactiveMutation;
use Kit\Reactive\Mutation;
use Kit\Reactive\NoArgs;
use RuntimeException;
use Spatie\LaravelData\Data;

/**
 * Writes a row, then throws: the row must roll back and nothing may push.
 *
 * @extends Mutation<NoArgs>
 */
#[ReactiveMutation('notes.fail')]
final class FailingMutation extends Mutation
{
    public static function args(): string
    {
        return NoArgs::class;
    }

    public function authorize(Authenticatable $user, Data $args): void
    {
        $this->gate($user)->authorize('create-notes');
    }

    public function handle(Data $args): mixed
    {
        Note::query()->create(['owner_id' => (string) auth()->id(), 'title' => 'doomed']);

        throw new RuntimeException('boom');
    }
}
