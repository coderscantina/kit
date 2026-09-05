<?php

declare(strict_types=1);

namespace Kit\Reactive\Invalidation;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Kit\Reactive\Contracts\Metrics;
use Kit\Reactive\Contracts\Registry;
use Kit\Reactive\Jobs\RecomputeComputation;

/**
 * One committed batch of changes. Resolves the affected computations and
 * queues a recompute per computation, coalescing bursts through the
 * per-computation debounce marker.
 */
final class Invalidate implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var array<int, array{table: string, id: string|null, before: array<string, string>|null, after: array<string, string>|null}> */
    public array $changes;

    /**
     * @param  array<int, Change>  $changes
     */
    public function __construct(array $changes, public int $mutationId)
    {
        $this->changes = array_map(fn (Change $change) => $change->toArray(), $changes);
    }

    /**
     * Bulk-write escape hatch: `Invalidate::table('messages')` after a
     * `withoutReactiveEvents()` block.
     */
    public static function table(string $table): void
    {
        $registry = app(Registry::class);

        self::dispatch([Change::table($table)], $registry->nextMutationId())
            ->onQueue((string) config('reactive.queue', 'reactive'));
    }

    public function handle(Registry $registry, Metrics $metrics, InvalidationResolver $resolver): void
    {
        $changes = array_map(fn (array $change) => Change::fromArray($change), $this->changes);
        $keys = $resolver->resolve($changes);

        $metrics->increment('invalidations');

        foreach ($keys as $key) {
            if (! $registry->debounce($key, (int) config('reactive.debounce_ms', 50))) {
                $metrics->increment('coalesced');

                continue;
            }

            RecomputeComputation::dispatch($key, $this->mutationId)
                ->onQueue((string) config('reactive.queue', 'reactive'));
        }
    }

    /**
     * @return array<int, Change>
     */
    public function changeObjects(): array
    {
        return array_map(fn (array $change) => Change::fromArray($change), $this->changes);
    }
}
