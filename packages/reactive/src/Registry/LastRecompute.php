<?php

declare(strict_types=1);

namespace Kit\Reactive\Registry;

/**
 * When a computation last recomputed, how long its query took, whether it
 * ran in the writer's request or on the queue, and whether the result
 * changed (and so was pushed). Stored next to the result so
 * `reactive:inspect` can answer "why did this not push?" without logs.
 */
final class LastRecompute
{
    public const string INLINE = 'inline';

    public const string QUEUE = 'queue';

    /**
     * @param  self::INLINE|self::QUEUE  $via
     */
    public function __construct(
        public readonly int $at,
        public readonly float $queryMs,
        public readonly string $via,
        public readonly bool $changed,
    ) {}

    /**
     * @return array{recomputed_at: string, recompute_ms: string, recomputed_via: string, recompute_changed: string}
     */
    public function toHash(): array
    {
        return [
            'recomputed_at' => (string) $this->at,
            'recompute_ms' => (string) $this->queryMs,
            'recomputed_via' => $this->via,
            'recompute_changed' => $this->changed ? '1' : '0',
        ];
    }

    /**
     * @param  array<string, string>  $hash
     */
    public static function fromHash(array $hash): ?self
    {
        if (! isset($hash['recomputed_at'], $hash['recompute_ms'], $hash['recomputed_via'])) {
            return null;
        }

        return new self(
            at: (int) $hash['recomputed_at'],
            queryMs: (float) $hash['recompute_ms'],
            via: $hash['recomputed_via'] === self::INLINE ? self::INLINE : self::QUEUE,
            changed: ($hash['recompute_changed'] ?? '0') === '1',
        );
    }
}
