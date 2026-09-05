<?php

declare(strict_types=1);

namespace Kit\Reactive;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate as GateFacade;

/**
 * A transactional write.
 *
 * The runner validates, authorizes, opens a REPEATABLE READ transaction with
 * deadlock retry, runs handle(), and on commit takes a mutation id and
 * dispatches the invalidations the models recorded. Authors never call
 * DB::transaction, afterCommit or dispatch here; a PHPStan rule enforces it.
 */
abstract class Mutation
{
    /**
     * @return array<string, array<int, mixed>|string>
     */
    abstract public function rules(): array;

    /**
     * @param  array<string, mixed>  $args
     */
    abstract public function authorize(Authenticatable $user, array $args): void;

    /**
     * Runs inside the transaction.
     *
     * @param  array<string, mixed>  $args
     */
    abstract public function handle(array $args): mixed;

    protected function gate(Authenticatable $user): Gate
    {
        return GateFacade::forUser($user);
    }

    /**
     * Lock the row and reload it from what is committed now, so the mutation
     * branches from the current state rather than from whatever an earlier
     * find() resolved a moment ago.
     *
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @return TModel
     */
    protected function lock(Model $model): Model
    {
        $fresh = $model->newQueryWithoutScopes()
            ->lockForUpdate()
            ->findOrFail($model->getKey());

        $model->setRawAttributes($fresh->getAttributes(), true);

        return $model;
    }
}
