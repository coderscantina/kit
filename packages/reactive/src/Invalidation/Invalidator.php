<?php

declare(strict_types=1);

namespace Kit\Reactive\Invalidation;

use Kit\Reactive\Contracts\Metrics;
use Kit\Reactive\Contracts\Registry;
use Kit\Reactive\Jobs\RecomputeComputation;
use Kit\Reactive\Runtime\RecomputeOutcome;
use Kit\Reactive\Runtime\Recomputer;
use Throwable;

/**
 * Where a committed batch of changes goes: through the writer's own
 * request, or onto the `reactive` queue.
 *
 * A write that touches a handful of computations is recomputed and pushed
 * right here, before the response returns. That takes the queue, its
 * pickup delay and a second job hop off the path the other session is
 * waiting on, and the writer's own screen gets its push before its
 * response. The price is the recompute's time inside the request, which is
 * why the count is capped: a write that wakes more computations than
 * `inline_recomputes` goes to the queue as one Invalidate job.
 *
 * Nothing here may fail the request. The row is committed by the time this
 * runs, so a recompute that throws or a lock that is busy hands the key to
 * the queue instead.
 */
final class Invalidator
{
    public function __construct(
        private readonly Registry $registry,
        private readonly Metrics $metrics,
        private readonly InvalidationResolver $resolver,
        private readonly Recomputer $recomputer,
    ) {}

    /**
     * @param  array<int, Change>  $changes
     */
    public function invalidate(array $changes, int $mutationId): void
    {
        $limit = (int) config('reactive.inline_recomputes', 4);
        $keys = $limit > 0 ? $this->resolver->resolve($changes) : null;

        if ($keys === null || count($keys) > $limit) {
            Invalidate::dispatch($changes, $mutationId)->onQueue($this->queue());

            return;
        }

        $this->metrics->increment('invalidations');

        foreach ($keys as $key) {
            if (! $this->registry->debounce($key, (int) config('reactive.debounce_ms', 50))) {
                $this->metrics->increment('coalesced');

                continue;
            }

            try {
                $outcome = $this->recomputer->run($key, $mutationId, (int) config('reactive.inline_lock_wait_ms', 200), inline: true);
            } catch (Throwable $e) {
                report($e);
                $outcome = RecomputeOutcome::Busy;
            }

            if ($outcome === RecomputeOutcome::Busy) {
                RecomputeComputation::dispatch($key, $mutationId)->onQueue($this->queue());

                continue;
            }

            $this->metrics->increment('inline');
        }
    }

    private function queue(): string
    {
        return (string) config('reactive.queue', 'reactive');
    }
}
