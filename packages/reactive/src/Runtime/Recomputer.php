<?php

declare(strict_types=1);

namespace Kit\Reactive\Runtime;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Kit\Reactive\Contracts\Metrics;
use Kit\Reactive\Contracts\Pusher;
use Kit\Reactive\Contracts\Registry;
use Kit\Reactive\Query;
use Kit\Reactive\Registry\Catalog;
use Kit\Reactive\Registry\LastRecompute;
use Kit\Reactive\Registry\Subscription;
use Spatie\LaravelData\Data;

/**
 * Recomputes one computation and pushes to its subscribers when the result
 * changed. The same code runs in the writer's request, right after commit,
 * and on the `reactive` queue; which one is the Invalidator's call.
 *
 * Runs the query once for the computation, however many clients watch it,
 * and pays for a per-user authorize() only when there is something new to
 * send.
 */
final class Recomputer
{
    public function __construct(
        private readonly Registry $registry,
        private readonly Metrics $metrics,
        private readonly Catalog $catalog,
        private readonly QueryRunner $runner,
    ) {}

    /**
     * `Busy` means another recompute holds the computation's lock and the
     * caller should try again later; everything else is settled.
     *
     * @param  int|null  $lockWaitMs  how long to wait for the lock; the registry's default when null
     * @param  bool  $inline  true in the writer's request, false on the queue; stored for `reactive:inspect`
     */
    public function run(string $computationKey, int $triggerMutationId, ?int $lockWaitMs = null, bool $inline = false): RecomputeOutcome
    {
        // Release the debounce before reading, so a commit that lands while
        // this recompute runs queues another one instead of being swallowed.
        $this->registry->releaseDebounce($computationKey);

        $computation = $this->registry->computation($computationKey);

        if ($computation === null) {
            return RecomputeOutcome::Gone;
        }

        if ($this->registry->subscribersOf($computationKey) === []) {
            $this->registry->forgetComputation($computationKey);

            return RecomputeOutcome::Gone;
        }

        $started = hrtime(true);

        // Read the counter before the query: everything committed up to this
        // id is visible to it, so the push may carry it as watermark.
        $mutationId = max($this->registry->currentMutationId(), $triggerMutationId);

        $class = $this->catalog->query($computation->query);

        if ($class === null) {
            $this->revokeAll($computationKey);
            $this->registry->forgetComputation($computationKey);

            return RecomputeOutcome::Gone;
        }

        // Resolved once and reused for every subscriber's authorize(). The
        // stored args are the canonical array; rebuild the Data the query
        // declared so handle() and authorize() see the same shape they do
        // on the request path.
        $query = app($class);
        $argsClass = $class::args();
        $args = $argsClass::from($computation->args);

        $computeStarted = hrtime(true);
        $result = $this->runner->compute($query, $args);
        $queryMs = round((hrtime(true) - $computeStarted) / 1_000_000, 2);
        $trace = fn (bool $changed) => new LastRecompute(time(), $queryMs, $inline ? LastRecompute::INLINE : LastRecompute::QUEUE, $changed);

        $ran = $this->registry->withLock($computationKey, function () use ($computationKey, $query, $args, $result, $mutationId, $trace): bool {
            $current = $this->registry->computation($computationKey);

            if ($current === null) {
                return true;
            }

            // Out of order: a later recompute already stored a newer watermark.
            if ($mutationId < $current->lastMutationId) {
                $this->metrics->increment('discarded');

                return true;
            }

            $this->metrics->increment('recomputes');

            if ($result->hash === $current->resultHash) {
                $this->metrics->increment('unchanged');
                $this->registry->touchComputation($computationKey, $mutationId, $trace(false));

                return true;
            }

            // Deps can change with data (a list that now spans another table).
            if ($result->tables !== $current->tables || $result->deps != $current->deps) {
                $current->tables = $result->tables;
                $current->deps = $result->deps;
                $this->registry->syncDeps($current);
            }

            $this->registry->updateComputation(
                $computationKey,
                $result->hash,
                Canonical::encode($result->result),
                $mutationId,
                $trace(true),
            );

            // Only now, with something new to send, is it worth paying for a
            // per-user authorization check.
            $this->pushToSubscribers($computationKey, $query, $args, $result->hash, $result->result, $mutationId);

            return true;
        }, $lockWaitMs);

        // Null means the lock was never taken, so nothing above ran. Another
        // worker owns this computation and is about to push its own result.
        if ($ran === null) {
            $this->metrics->increment('lock_timeout');

            return RecomputeOutcome::Busy;
        }

        $this->metrics->recordLatency((hrtime(true) - $started) / 1_000_000);

        return RecomputeOutcome::Done;
    }

    /**
     * Authorize and push every subscriber of this computation.
     *
     * The users are loaded in one query rather than one per subscription:
     * a popular computation has hundreds of watchers, and a find() each
     * would put the recompute's cost back on the subscriber count that
     * sharing the computation was meant to remove.
     *
     * @param  Query<Data>  $query
     */
    private function pushToSubscribers(
        string $computationKey,
        Query $query,
        Data $args,
        string $hash,
        mixed $result,
        int $mutationId,
    ): void {
        $subscriptions = [];

        foreach ($this->registry->subscribersOf($computationKey) as $id) {
            $subscription = $this->registry->get($id);

            if ($subscription !== null) {
                $subscriptions[] = $subscription;
            }
        }

        $users = $this->hydrateUsers(array_map(fn (Subscription $s) => $s->userId, $subscriptions));

        foreach ($subscriptions as $subscription) {
            $user = $users[$subscription->userId] ?? null;

            if ($user === null) {
                $this->revoke($subscription->id);

                continue;
            }

            try {
                $this->runner->authorize($query, $user, $args);
            } catch (AuthorizationException) {
                // Access was withdrawn (role change, row gone): drop the
                // subscription and say so instead of pushing.
                $this->revoke($subscription->id);

                continue;
            }

            $this->pusher()->push($subscription, $mutationId, $hash, $result);
        }
    }

    /**
     * The query itself is gone (renamed, deleted) while clients still watch
     * it. Nothing can be recomputed, so everyone is told.
     */
    private function revokeAll(string $computationKey): void
    {
        foreach ($this->registry->subscribersOf($computationKey) as $id) {
            $this->revoke($id);
        }
    }

    private function revoke(string $id): void
    {
        $subscription = $this->registry->get($id);

        if ($subscription === null) {
            return;
        }

        $this->registry->delete($id);
        $this->pusher()->revoke($subscription);
        $this->metrics->increment('revoked');
    }

    /**
     * Resolved per push rather than in the constructor: `Reactive::fake()`
     * swaps the binding after the container may already hold this service.
     */
    private function pusher(): Pusher
    {
        return app(Pusher::class);
    }

    /**
     * @param  array<int, string>  $userIds
     * @return array<string, Authenticatable>
     */
    private function hydrateUsers(array $userIds): array
    {
        $ids = array_values(array_unique($userIds));

        if ($ids === []) {
            return [];
        }

        /** @var class-string<Model&Authenticatable> $model */
        $model = config('reactive.user_model');

        $users = [];

        foreach ($model::query()->whereKey($ids)->get() as $user) {
            if ($user instanceof Authenticatable) {
                $users[(string) $user->getKey()] = $user;
            }
        }

        return $users;
    }
}
