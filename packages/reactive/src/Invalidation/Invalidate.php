<?php

declare(strict_types=1);

namespace Kit\Reactive\Invalidation;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Kit\Reactive\Contracts\Registry;
use Kit\Reactive\Jobs\RecomputeSubscription;

/**
 * One committed batch of changes. Resolves the affected subscriptions and
 * queues a recompute per subscription, coalescing bursts through the
 * per-subscription debounce marker.
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

    public function handle(Registry $registry, InvalidationResolver $resolver): void
    {
        $changes = array_map(fn (array $change) => Change::fromArray($change), $this->changes);
        $ids = $resolver->resolve($changes);

        $registry->incrementMetric('invalidations');

        foreach ($ids as $id) {
            if (! $registry->debounce($id, (int) config('reactive.debounce_ms', 50))) {
                $registry->incrementMetric('coalesced');

                continue;
            }

            RecomputeSubscription::dispatch($id, $this->mutationId)
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
