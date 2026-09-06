<?php

declare(strict_types=1);

namespace Kit\Reactive\Runtime;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Kit\Reactive\Attributes\ReactiveMutation;
use Kit\Reactive\Concurrency\VersionConflict;
use Kit\Reactive\Contracts\Registry;
use Kit\Reactive\Invalidation\ChangeBuffer;
use Kit\Reactive\Mutation;
use ReflectionClass;
use Spatie\LaravelData\Data;
use Throwable;

/**
 * The mutation pipeline (§4.2): build the args class → authorize → REPEATABLE READ
 * transaction retried on deadlock/lock-wait with jittered backoff → on
 * commit the change buffer takes a mutation id and hands the batch to the
 * Invalidator → {result, mutationId}. A VersionConflict from inside the
 * transaction is not retried: the row moved, and the caller has to see it.
 */
final class MutationRunner
{
    private const int ATTEMPTS = 3;

    /** MySQL/MariaDB: ER_LOCK_DEADLOCK and ER_LOCK_WAIT_TIMEOUT. */
    private const array RETRYABLE_ERRNOS = [1213, 1205];

    public function __construct(
        private readonly Registry $registry,
        private readonly ChangeBuffer $buffer,
    ) {}

    /**
     * @param  Mutation<Data>  $mutation
     * @param  array<string, mixed>  $args  raw input
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function run(Mutation $mutation, Authenticatable $user, array $args): MutationResult
    {
        $class = $mutation::args();
        $validated = $class::validateAndCreate($args);

        $mutation->authorize($user, $validated);

        try {
            $raw = $this->transaction(fn () => $mutation->handle($validated));
        } catch (VersionConflict $conflict) {
            // Rolled back already. The client gets the row as it is now, in
            // the shape this mutation's result would have had.
            throw $conflict->presentAs($this->resultClass($mutation));
        }

        // The buffer INCRs the counter when it flushes on commit. A mutation
        // that changed no tracked row still gets an id, so the client can
        // reconcile its optimistic state against a real watermark.
        $mutationId = $this->buffer->takeCommittedMutationId() ?? $this->registry->nextMutationId();

        return new MutationResult(Canonical::normalize($raw), $mutationId);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function transaction(callable $callback): mixed
    {
        $connection = DB::connection();

        for ($attempt = 1; ; $attempt++) {
            $this->setIsolationLevel($connection);
            $connection->beginTransaction();

            try {
                $result = $callback();
                $connection->commit();

                return $result;
            } catch (Throwable $e) {
                $connection->rollBack();

                if ($attempt < self::ATTEMPTS && $this->isRetryable($e)) {
                    // Jittered so two colliding mutations do not retry in lockstep.
                    usleep(random_int(20, 100) * 1000 * $attempt);

                    continue;
                }

                throw $e;
            }
        }
    }

    /**
     * REPEATABLE READ is MySQL's default and the level the design assumes;
     * set it explicitly so a session-level override cannot change it.
     * SERIALIZABLE is deliberately not used: gap locks deadlock under load.
     */
    private function setIsolationLevel(Connection $connection): void
    {
        if ($connection->transactionLevel() === 0 && in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }
    }

    /**
     * @param  Mutation<Data>  $mutation
     * @return class-string<Data>|null
     */
    private function resultClass(Mutation $mutation): ?string
    {
        $attributes = (new ReflectionClass($mutation))->getAttributes(ReactiveMutation::class);

        /** @var class-string<Data>|null $result */
        $result = $attributes === [] ? null : $attributes[0]->newInstance()->result;

        return $result;
    }

    private function isRetryable(Throwable $e): bool
    {
        if (! $e instanceof QueryException) {
            return false;
        }

        $errno = $e->errorInfo[1] ?? null;

        return in_array($errno, self::RETRYABLE_ERRNOS, true)
            || str_contains(strtolower($e->getMessage()), 'deadlock');
    }
}
