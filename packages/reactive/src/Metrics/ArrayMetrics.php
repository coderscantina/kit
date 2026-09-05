<?php

declare(strict_types=1);

namespace Kit\Reactive\Metrics;

use Kit\Reactive\Contracts\Metrics;

/**
 * In-memory counters for the sqlite test suite and `Reactive::fake()`.
 */
final class ArrayMetrics implements Metrics
{
    private const int LATENCY_SAMPLES = 200;

    /** @var array<string, int> */
    private array $counters = [];

    /** @var array<int, float> */
    private array $latencies = [];

    public function increment(string $name, int $by = 1): void
    {
        $this->counters[$name] = ($this->counters[$name] ?? 0) + $by;
    }

    public function recordLatency(float $milliseconds): void
    {
        $this->latencies[] = $milliseconds;
        $this->latencies = array_slice($this->latencies, -self::LATENCY_SAMPLES);
    }

    public function snapshot(): array
    {
        return ['metrics' => $this->counters, 'latencies' => $this->latencies];
    }

    public function flush(): void
    {
        $this->counters = [];
        $this->latencies = [];
    }
}
