<?php

declare(strict_types=1);

namespace Kit\Reactive\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Kit\Reactive\Runtime\RecomputeOutcome;
use Kit\Reactive\Runtime\Recomputer;

/**
 * The queued half of a recompute (§4.7): the same Recomputer the writer's
 * request runs, for the keys the request handed off. A job that finds the
 * lock busy tries once more a second later, because the holder may not
 * have seen this mutation yet.
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

    public function handle(Recomputer $recomputer): void
    {
        $outcome = $recomputer->run($this->computationKey, $this->triggerMutationId);

        if ($outcome === RecomputeOutcome::Busy && ! $this->isLockRetry) {
            self::dispatch($this->computationKey, $this->triggerMutationId, true)
                ->onQueue((string) config('reactive.queue', 'reactive'))
                ->delay(self::LOCK_RETRY_DELAY);
        }
    }
}
