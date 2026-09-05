<?php

declare(strict_types=1);

namespace Kit\Reactive\Runtime;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Kit\Reactive\Query;
use Throwable;

/**
 * The query pipeline (§4.1): validate → authorize → track → handle →
 * collect tables → union with reads() → serialize.
 */
final class QueryRunner
{
    public function __construct(
        private readonly TableTracker $tracker,
    ) {}

    /**
     * @param  array<string, mixed>  $args
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function run(Query $query, Authenticatable $user, array $args): QueryResult
    {
        $validated = Validator::make($args, $query->rules())->validate();

        $query->authorize($user, $validated);

        $this->tracker->start();

        try {
            $raw = $query->handle($validated);
        } catch (Throwable $e) {
            $this->tracker->stop();
            throw $e;
        }

        $tables = $this->tracker->stop();
        $result = Canonical::normalize($raw);

        return new QueryResult(
            result: $result,
            hash: Canonical::hash($result),
            args: $validated,
            tables: $tables,
            deps: $query->reads($validated),
        );
    }
}
