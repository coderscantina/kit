<?php

declare(strict_types=1);

namespace App\Services\Ai\Dto;

/**
 * What one completed stream cost, as the provider reported it. Cost is in
 * USD and comes from OpenRouter directly, so it already accounts for the
 * upstream provider's own pricing and any caching discount.
 */
final readonly class AiUsage
{
    public function __construct(
        public string $model,
        public int $promptTokens = 0,
        public int $completionTokens = 0,
        public float $cost = 0.0,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  the `usage` object of the final chunk
     */
    public static function fromPayload(string $model, array $payload): self
    {
        return new self(
            model: $model,
            promptTokens: (int) ($payload['prompt_tokens'] ?? 0),
            completionTokens: (int) ($payload['completion_tokens'] ?? 0),
            cost: (float) ($payload['cost'] ?? 0.0),
        );
    }

    public function totalTokens(): int
    {
        return $this->promptTokens + $this->completionTokens;
    }

    /**
     * @return array{model: string, promptTokens: int, completionTokens: int, totalTokens: int, cost: float}
     */
    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'promptTokens' => $this->promptTokens,
            'completionTokens' => $this->completionTokens,
            'totalTokens' => $this->totalTokens(),
            'cost' => $this->cost,
        ];
    }
}
