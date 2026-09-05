<?php

declare(strict_types=1);

namespace Kit\Reactive\Contracts;

/**
 * Counters and recompute latencies, kept apart from the {@see Registry} so
 * the registry contract is only about subscriptions and computations.
 *
 * Everything here is best effort. A dropped counter never changes what a
 * client sees, so implementations do not need to be transactional.
 */
interface Metrics
{
    public function increment(string $name, int $by = 1): void;

    public function recordLatency(float $milliseconds): void;

    /**
     * @return array{metrics: array<string, int>, latencies: array<int, float>}
     */
    public function snapshot(): array;

    /** Reset every counter; tests and `reactive:gc --flush`. */
    public function flush(): void;
}
