<?php

declare(strict_types=1);

namespace Kit\Reactive\Runtime;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Kit\Reactive\Contracts\Metrics;
use Kit\Reactive\Contracts\Registry;
use Kit\Reactive\Query;
use Kit\Reactive\Registry\Computation;
use Kit\Reactive\Registry\Subscription;
use Spatie\LaravelData\Data;

/**
 * Turns "this user wants to watch this query with these args" into a
 * subscription pointing at a shared computation.
 *
 * The rule lives here rather than in the controller so that `/rq/subscribe`
 * and `Reactive::fake()->subscribe()` cannot disagree about who pays for the
 * query: the first subscriber to a question runs it, every later one reads
 * the stored result and pays only for its own `authorize()`.
 */
final class Subscriber
{
    public function __construct(
        private readonly Registry $registry,
        private readonly Metrics $metrics,
        private readonly QueryRunner $queries,
    ) {}

    /**
     * @param  Query<Data>  $query
     * @param  array<string, mixed>  $args  raw input
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function subscribe(Query $query, string $name, Authenticatable $user, array $args): SubscribeResult
    {
        $validated = $this->queries->validate($query, $args);
        $this->queries->authorize($query, $user, $validated);

        $computation = $this->computationFor($query, $name, $validated);

        $subscription = new Subscription(
            id: (string) Str::ulid(),
            computationKey: $computation->key,
            query: $name,
            args: $computation->args,
            userId: (string) $user->getAuthIdentifier(),
            createdAt: time(),
        );

        $this->registry->put($subscription);
        $this->metrics->increment('subscribes');

        return new SubscribeResult($subscription, $computation);
    }

    /**
     * The computation for this query and these args, created on first use.
     *
     * @param  Query<Data>  $query
     */
    public function computationFor(Query $query, string $name, Data $args): Computation
    {
        // The canonical array, not the object: the registry stores plain
        // arrays, and two subscribers whose payloads differ only in key
        // order must land on one computation.
        $canonical = $args->toArray();
        $key = Computation::keyFor($name, $canonical);
        $existing = $this->registry->computation($key);

        if ($existing !== null) {
            $this->metrics->increment('shared');

            return $existing;
        }

        // Counter before the query: everything committed up to this id is in
        // the result, so a push with a higher id is strictly newer.
        $mutationId = $this->registry->currentMutationId();
        $outcome = $this->queries->compute($query, $args);

        $computation = new Computation(
            key: $key,
            query: $name,
            args: $canonical,
            resultHash: $outcome->hash,
            result: Canonical::encode($outcome->result),
            lastMutationId: $mutationId,
            createdAt: time(),
            tables: $outcome->tables,
            deps: $outcome->deps,
        );

        $this->registry->putComputation($computation);
        $this->metrics->increment('computed');

        return $computation;
    }
}
