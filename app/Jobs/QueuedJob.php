<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\Ambient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Base for every queued job. Subclasses implement execute() and
 * handleFailure(); the queue lifecycle stays here.
 */
abstract class QueuedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * Retry spacing in seconds. Back-to-back retries hammer a resource that
     * just failed; spreading them gives it a chance to recover.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * Long-lived workers leak ambient state between jobs. Snapshot every
     * binding listed in config('kit.ambient_bindings') and restore it in
     * finally, so a job that binds a scope can never leak it into the next.
     */
    final public function handle(): void
    {
        $snapshot = Ambient::snapshot();

        try {
            $this->execute();
        } finally {
            Ambient::restore($snapshot);
        }
    }

    /**
     * Runs once after the final attempt, also for Error and TypeError.
     */
    public function failed(Throwable $e): void
    {
        $this->handleFailure($e);
    }

    abstract protected function execute(): void;

    abstract protected function handleFailure(Throwable $e): void;
}
