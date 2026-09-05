<?php

declare(strict_types=1);

namespace App\Services\Ai\Dto;

/**
 * One entry of the provider's model catalogue, normalised. `supportsTools`
 * decides whether an action's tools are sent at all, so an unknown model is
 * assumed not to support them rather than failing mid-stream.
 */
final readonly class AiModel
{
    /**
     * @param  array<int, string>  $capabilities
     */
    public function __construct(
        public string $id,
        public string $name,
        public int $contextWindow = 4096,
        public int $maxOutputTokens = 4096,
        public float $inputCost = 0.0,
        public float $outputCost = 0.0,
        public array $capabilities = ['text'],
        public bool $supportsTools = false,
        public bool $supportsVision = false,
        public bool $supportsJsonMode = false,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  one entry of GET /models
     */
    public static function fromPayload(array $payload): self
    {
        /** @var array<int, string> $parameters */
        $parameters = $payload['supported_parameters'] ?? [];
        $modality = (string) data_get($payload, 'architecture.modality', 'text->text');
        $vision = str_contains($modality, 'image');
        $tools = in_array('tools', $parameters, true);

        return new self(
            id: (string) $payload['id'],
            name: (string) ($payload['name'] ?? $payload['id']),
            contextWindow: (int) ($payload['context_length'] ?? 4096),
            maxOutputTokens: (int) (data_get($payload, 'top_provider.max_completion_tokens') ?? 4096),
            // Prices come per token as decimal strings; per million reads.
            inputCost: (float) data_get($payload, 'pricing.prompt', 0) * 1_000_000,
            outputCost: (float) data_get($payload, 'pricing.completion', 0) * 1_000_000,
            capabilities: array_values(array_filter([
                'text',
                $vision ? 'vision' : null,
                $tools ? 'tools' : null,
            ])),
            supportsTools: $tools,
            supportsVision: $vision,
            supportsJsonMode: in_array('response_format', $parameters, true)
                || in_array('structured_outputs', $parameters, true),
        );
    }
}
