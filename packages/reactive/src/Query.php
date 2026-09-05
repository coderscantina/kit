<?php

declare(strict_types=1);

namespace Kit\Reactive;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate as GateFacade;
use Spatie\LaravelData\Data;

/**
 * A subscribable server-side query function.
 *
 * The runner guarantees the order: build the args through
 * `Data::validateAndCreate()`, authorize(), track the tables handle() reads,
 * union them with reads(), serialize. Subclasses only fill in the methods;
 * nothing here needs to be called by hand.
 *
 * The args class appears twice in a subclass, in `@extends Query<XArgs>` and
 * in `args()`. PHPStan checks the two against each other, so they cannot
 * drift. Both are needed: PHP forbids narrowing a parameter type in an
 * override, so the signatures have to stay `Data $args` and the concrete
 * type reaches PHPStan through the template.
 *
 * @template TArgs of Data
 */
abstract class Query
{
    /**
     * The Data class the args are validated into.
     *
     * @return class-string<TArgs>
     */
    abstract public static function args(): string;

    /**
     * Throw (AuthorizationException) when the user may not run this query
     * with these args. An empty body fails the architecture test.
     *
     * @param  TArgs  $args
     */
    abstract public function authorize(Authenticatable $user, Data $args): void;

    /**
     * @param  TArgs  $args
     */
    abstract public function handle(Data $args): mixed;

    /**
     * Declared dependencies on top of the tables the SQL tracker sees.
     *
     * @param  TArgs  $args
     * @return array<int, Dep>
     */
    public function reads(Data $args): array
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
