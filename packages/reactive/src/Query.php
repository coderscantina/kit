<?php

declare(strict_types=1);

namespace Kit\Reactive;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate as GateFacade;

/**
 * A subscribable server-side query function.
 *
 * The runner guarantees the order: validate args against rules(),
 * authorize(), track the tables handle() reads, union them with reads(),
 * serialize. Subclasses only fill in the four methods; nothing here needs
 * to be called by hand.
 */
abstract class Query
{
    /**
     * Laravel validation rules for the args. Types for the client are
     * derived from these by types:generate.
     *
     * @return array<string, array<int, mixed>|string>
     */
    abstract public function rules(): array;

    /**
     * Throw (AuthorizationException) when the user may not run this query
     * with these args. An empty body fails the architecture test.
     *
     * @param  array<string, mixed>  $args
     */
    abstract public function authorize(Authenticatable $user, array $args): void;

    /**
     * @param  array<string, mixed>  $args
     */
    abstract public function handle(array $args): mixed;

    /**
     * Declared dependencies on top of the tables the SQL tracker sees.
     *
     * @param  array<string, mixed>  $args
     * @return array<int, Dep>
     */
    public function reads(array $args): array
    {
        return [];
    }

    /**
     * The gate for the user, so authorize() reads as
     * `$this->gate($user)->authorize('view', $model)`.
     */
    protected function gate(Authenticatable $user): Gate
    {
        return GateFacade::forUser($user);
    }
}
