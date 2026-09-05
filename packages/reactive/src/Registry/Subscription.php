<?php

declare(strict_types=1);

namespace Kit\Reactive\Registry;

/**
 * One client watching one computation.
 *
 * The subscription owns everything that is per user: the channel it is pushed
 * on, the identity `authorize()` is re-checked against, and the cleanup that
 * fires when its tab closes. The result and the dependency sets live on the
 * shared {@see Computation} it points at.
 *
 * `query` and `args` are copies of the computation's, kept here so channel
 * auth, `/rq/unsubscribe` and the test assertions read one hash instead of
 * two. They cannot drift: the computation key is derived from them.
 */
final class Subscription
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __construct(
        public readonly string $id,
        public readonly string $computationKey,
        public readonly string $query,
        public readonly array $args,
        public readonly string $userId,
        public readonly int $createdAt,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toHash(): array
    {
        return [
            'computation' => $this->computationKey,
            'query' => $this->query,
            'args' => json_encode($this->args, JSON_THROW_ON_ERROR),
            'user_id' => $this->userId,
            'created_at' => (string) $this->createdAt,
        ];
    }

    /**
     * @param  array<string, string>  $hash
     */
    public static function fromHash(string $id, array $hash): self
    {
        /** @var array<string, mixed> $args */
        $args = json_decode($hash['args'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);

        return new self(
            id: $id,
            computationKey: $hash['computation'],
            query: $hash['query'],
            args: $args,
            userId: $hash['user_id'],
            createdAt: (int) ($hash['created_at'] ?? 0),
        );
    }
}
