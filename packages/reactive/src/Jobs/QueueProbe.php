<?php

declare(strict_types=1);

namespace Kit\Reactive\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Answers "is anything consuming the reactive queue, and how old is it?"
 *
 * `kit:doctor` puts one of these on the queue and waits for the answer in
 * the cache. A dead worker and a broken layer look the same from a
 * browser: nothing happens. A worker that predates the last code change
 * looks the same too, and is worse, because it runs old code with a
 * straight face. The answer carries the worker's process start, so the
 * doctor can compare it with the newest file the layer reads.
 */
final class QueueProbe implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public string $token,
    ) {}

    public static function key(string $token): string
    {
        return "rq:probe:{$token}";
    }

    public function handle(): void
    {
        Cache::put(self::key($this->token), [
            'pid' => getmypid(),
            'host' => gethostname(),
            // The process start for a long-running worker; the request time
            // for anything that runs jobs inline.
            'startedAt' => (int) ($_SERVER['REQUEST_TIME'] ?? time()),
            'answeredAt' => microtime(true),
        ], 60);
    }
}
