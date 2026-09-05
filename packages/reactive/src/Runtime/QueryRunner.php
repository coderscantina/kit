<?php

declare(strict_types=1);

namespace Kit\Reactive\Runtime;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use Kit\Reactive\Query;
use Spatie\LaravelData\Data;
use Throwable;

/**
 * The query pipeline (§4.1): validate → authorize → track → handle →
 * collect tables → union with reads() → serialize.
 *
 * The three steps are also callable on their own, because they run at
 * different rates. `handle()` sees only the args, so its result is shared by
 * every subscriber asking the same question and is computed once per change.
 * `authorize()` is per user and is re-checked before each push.
 */
final class QueryRunner
{
    public function __construct(
        private readonly TableTracker $tracker,
    ) {}

    /**
     * @param  Query<Data>  $query
     * @param  array<string, mixed>  $args  raw input
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function run(Query $query, Authenticatable $user, array $args): QueryResult
    {
        $validated = $this->validate($query, $args);

        $this->authorize($query, $user, $validated);

        return $this->compute($query, $validated);
    }

    /**
     * Build the query's args class from the raw input. laravel-data runs the
     * property rules, so a bad payload throws ValidationException and the
     * controller answers 422 exactly as it did with rules().
     *
     * @param  Query<Data>  $query
     * @param  array<string, mixed>  $args  raw input
     *
     * @throws ValidationException
     */
    public function validate(Query $query, array $args): Data
    {
        $class = $query::args();

        return $class::validateAndCreate($args);
    }

    /**
     * @param  Query<Data>  $query
     *
     * @throws AuthorizationException
     */
    public function authorize(Query $query, Authenticatable $user, Data $args): void
    {
        $query->authorize($user, $args);
    }

    /**
     * Run the query and record what it read. No user is involved: a query
     * that needs one takes it through its args.
     *
     * @param  Query<Data>  $query
     */
    public function compute(Query $query, Data $args): QueryResult
    {
        $this->tracker->start();

        try {
            $raw = $query->handle($args);
        } catch (Throwable $e) {
            $this->tracker->stop();
            throw $e;
        }

        $tables = $this->tracker->stop();
        $result = Canonical::normalize($raw);

        return new QueryResult(
            result: $result,
            hash: Canonical::hash($result),
            args: $args,
            tables: $tables,
            deps: $query->reads($args),
        );
    }
}
