<?php

declare(strict_types=1);

namespace Kit\Reactive\Jobs;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Kit\Reactive\Contracts\Metrics;
use Kit\Reactive\Contracts\Pusher;
use Kit\Reactive\Contracts\Registry;
use Kit\Reactive\Query;
use Kit\Reactive\Registry\Catalog;
use Kit\Reactive\Registry\Subscription;
use Kit\Reactive\Runtime\Canonical;
use Kit\Reactive\Runtime\QueryRunner;
use Spatie\LaravelData\Data;

/**
 * The recompute worker (§4.7). Runs the query once for the computation,
 * however many clients watch it, and pushes only when the result changed.
 */
final class RecomputeComputation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** Seconds before a job that lost the lock tries again. */
    private const int LOCK_RETRY_DELAY = 1;

    public int $tries = 3;

    public function __construct(
        public string $computationKey,
        public int $triggerMutationId,
        public bool $isLockRetry = false,
    ) {}

    public function handle(Registry $registry, Metrics $metrics, Catalog $catalog, QueryRunner $runner, Pusher $pusher): void
    {
        // Release the debounce before reading, so a commit that lands while
        // this recompute runs queues another one instead of being swallowed.
        $registry->releaseDebounce($this->computationKey);

        $computation = $registry->computation($this->computationKey);

        if ($computation === null) {
            return;
        }

        if ($registry->subscribersOf($this->computationKey) === []) {
            $registry->forgetComputation($this->computationKey);

            return;
        }

        $started = hrtime(true);

        // Read the counter before the query: everything committed up to this
        // id is visible to it, so the push may carry it as watermark.
        $mutationId = max($registry->currentMutationId(), $this->triggerMutationId);

        $class = $catalog->query($computation->query);

        if ($class === null) {
            $this->revokeAll($registry, $metrics, $pusher);
            $registry->forgetComputation($this->computationKey);

            return;
        }

        // Resolved once and reused for every subscriber's authorize(). The
        // stored args are the canonical array; rebuild the Data the query
        // declared so handle() and authorize() see the same shape they do
        // on the request path.
        $query = app($class);
        $argsClass = $class::args();
        $args = $argsClass::from($computation->args);

        $result = $runner->compute($query, $args);

        $ran = $registry->withLock($this->computationKey, function () use ($registry, $metrics, $pusher, $runner, $query, $args, $result, $mutationId): bool {
            $current = $registry->computation($this->computationKey);

            if ($current === null) {
                return true;
            }

            // Out of order: a later recompute already stored a newer watermark.
            if ($mutationId < $current->lastMutationId) {
                $metrics->increment('discarded');

                return true;
            }

            $metrics->increment('recomputes');

            if ($result->hash === $current->resultHash) {
                $metrics->increment('unchanged');
                $registry->touchComputation($this->computationKey, $mutationId);

                return true;
            }

            // Deps can change with data (a list that now spans another table).
            if ($result->tables !== $current->tables || $result->deps != $current->deps) {
                $current->tables = $result->tables;
                $current->deps = $result->deps;
                $registry->syncDeps($current);
            }

            $registry->updateComputation(
                $this->computationKey,
                $result->hash,
                Canonical::encode($result->result),
                $mutationId,
            );

            // Only now, with something new to send, is it worth paying for a
            // per-user authorization check.
            $this->pushToSubscribers($registry, $metrics, $pusher, $runner, $query, $args, $result->hash, $result->result, $mutationId);

            return true;
        });

        // Null means the lock was never taken, so nothing above ran. Another
        // worker owns this computation and is about to push its own result;
        // one retry covers the case where it did not see our mutation yet.
        if ($ran === null) {
            $metrics->increment('lock_timeout');

            if (! $this->isLockRetry) {
                self::dispatch($this->computationKey, $mutationId, true)
                    ->onQueue((string) config('reactive.queue', 'reactive'))
                    ->delay(self::LOCK_RETRY_DELAY);
            }

            return;
        }

        $metrics->recordLatency((hrtime(true) - $started) / 1_000_000);
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
        Registry $registry,
        Metrics $metrics,
        Pusher $pusher,
        QueryRunner $runner,
        Query $query,
        Data $args,
        string $hash,
        mixed $result,
        int $mutationId,
    ): void {
        $subscriptions = [];

        foreach ($registry->subscribersOf($this->computationKey) as $id) {
            $subscription = $registry->get($id);

            if ($subscription !== null) {
                $subscriptions[] = $subscription;
            }
        }

        $users = $this->hydrateUsers(array_map(fn (Subscription $s) => $s->userId, $subscriptions));

        foreach ($subscriptions as $subscription) {
            $user = $users[$subscription->userId] ?? null;

            if ($user === null) {
                $this->revoke($registry, $metrics, $pusher, $subscription->id);

                continue;
            }

            try {
                $runner->authorize($query, $user, $args);
            } catch (AuthorizationException) {
                // Access was withdrawn (role change, row gone): drop the
                // subscription and say so instead of pushing.
                $this->revoke($registry, $metrics, $pusher, $subscription->id);

                continue;
            }

            $pusher->push($subscription, $mutationId, $hash, $result);
        }
    }

    /**
     * The query itself is gone (renamed, deleted) while clients still watch
     * it. Nothing can be recomputed, so everyone is told.
     */
    private function revokeAll(Registry $registry, Metrics $metrics, Pusher $pusher): void
    {
        foreach ($registry->subscribersOf($this->computationKey) as $id) {
            $this->revoke($registry, $metrics, $pusher, $id);
        }
    }

    private function revoke(Registry $registry, Metrics $metrics, Pusher $pusher, string $id): void
    {
        $subscription = $registry->get($id);

        if ($subscription === null) {
            return;
        }

        $registry->delete($id);
        $pusher->revoke($subscription);
        $metrics->increment('revoked');
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
