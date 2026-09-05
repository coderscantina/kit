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
use Illuminate\Validation\ValidationException;
use Kit\Reactive\Contracts\Pusher;
use Kit\Reactive\Contracts\Registry;
use Kit\Reactive\Registry\Catalog;
use Kit\Reactive\Runtime\QueryRunner;

/**
 * The recompute worker (§4.7): re-run the subscribed query through the same
 * pipeline as the original subscribe, and push when the result changed.
 */
final class RecomputeSubscription implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public string $subscriptionId,
        public int $triggerMutationId,
    ) {}

    public function handle(Registry $registry, Catalog $catalog, QueryRunner $runner, Pusher $pusher): void
    {
        // Release the debounce before reading, so a commit that lands while
        // this recompute runs queues another one instead of being swallowed.
        $registry->releaseDebounce($this->subscriptionId);

        $subscription = $registry->get($this->subscriptionId);

        if ($subscription === null) {
            return;
        }

        $started = hrtime(true);

        // Read the counter before the query: everything committed up to this
        // id is visible to the query, so the push may carry it as watermark.
        $mutationId = max($registry->currentMutationId(), $this->triggerMutationId);

        $class = $catalog->query($subscription->query);
        $user = $this->hydrateUser($subscription->userId);

        if ($class === null || $user === null) {
            $registry->delete($subscription->id);
            $pusher->revoke($subscription);

            return;
        }

        try {
            $result = $runner->run(app($class), $user, $subscription->args);
        } catch (AuthorizationException|ValidationException) {
            // Access was withdrawn (role change, row gone): drop the
            // subscription and tell the client instead of pushing stale data.
            $registry->delete($subscription->id);
            $pusher->revoke($subscription);
            $registry->incrementMetric('revoked');

            return;
        }

        $registry->withLock($subscription->id, function () use ($registry, $pusher, $subscription, $result, $mutationId): void {
            $current = $registry->get($subscription->id);

            if ($current === null) {
                return;
            }

            // Out of order: a later recompute already stored a newer watermark.
            if ($mutationId < $current->lastMutationId) {
                $registry->incrementMetric('discarded');

                return;
            }

            $registry->incrementMetric('recomputes');

            if ($result->hash === $current->resultHash) {
                $registry->incrementMetric('unchanged');
                $registry->updateResult($subscription->id, $current->resultHash, $mutationId);

                return;
            }

            // Deps can change with data (a list that now spans another table).
            if ($result->tables !== $current->tables || $result->deps != $current->deps) {
                $current->tables = $result->tables;
                $current->deps = $result->deps;
                $registry->syncDeps($current);
            }

            $registry->updateResult($subscription->id, $result->hash, $mutationId);
            $pusher->push($subscription, $mutationId, $result->hash, $result->result);
        });

        $registry->recordLatency((hrtime(true) - $started) / 1_000_000);
    }

    private function hydrateUser(string $userId): ?Authenticatable
    {
        /** @var class-string<Model&Authenticatable> $model */
        $model = config('reactive.user_model');

        $user = $model::query()->find($userId);

        return $user instanceof Authenticatable ? $user : null;
    }
}
