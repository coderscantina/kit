<?php

declare(strict_types=1);

namespace Kit\Reactive\Metrics;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use Kit\Reactive\Contracts\Metrics;

/**
 * Redis-backed counters: one hash `rq:metrics` and a capped list
 * `rq:latency` holding the most recent recompute durations.
 */
final class RedisMetrics implements Metrics
{
    private const int LATENCY_SAMPLES = 200;

    public function __construct(
        private readonly RedisFactory $redis,
        private readonly string $connection,
    ) {}

    public function increment(string $name, int $by = 1): void
    {
        $this->redis()->hincrby('rq:metrics', $name, $by);
    }

    public function recordLatency(float $milliseconds): void
    {
        $this->redis()->lpush('rq:latency', (string) round($milliseconds, 2));
        $this->redis()->ltrim('rq:latency', 0, self::LATENCY_SAMPLES - 1);
    }

    public function snapshot(): array
    {
        /** @var array<string, string> $counters */
        $counters = $this->redis()->hgetall('rq:metrics') ?: [];
        /** @var array<int, string> $latencies */
        $latencies = $this->redis()->lrange('rq:latency', 0, -1) ?: [];

        return [
            'metrics' => array_map('intval', $counters),
            'latencies' => array_map('floatval', $latencies),
        ];
    }

    public function flush(): void
    {
        $this->redis()->del('rq:metrics', 'rq:latency');
    }

    private function redis(): Connection
    {
        return $this->redis->connection($this->connection);
    }
}
