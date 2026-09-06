<?php

declare(strict_types=1);

namespace Tests\Fixtures\Reactive;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Kit\Reactive\Registry\Catalog;

/**
 * Registers the fixture queries/mutations and their gates for a test.
 * Abilities are plain gate closures so the fixtures need no feature roles.
 */
final class ReactiveFixtures
{
    public static function install(): void
    {
        Note::migrate();

        config(['reactive.classes' => [ListNotes::class, AllNotes::class, CreateNote::class, RenameNote::class, EditNote::class, FailingMutation::class]]);
        app(Catalog::class)->reset();

        Gate::define('view-notes', fn (User $user, string $ownerId) => $user->is_root || $user->id === $ownerId);
        Gate::define('view-all-notes', fn (User $user) => $user->is_root);
        Gate::define('create-notes', fn (User $user) => $user->email_verified_at !== null);
    }
}
