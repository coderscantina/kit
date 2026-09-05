<?php

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use App\Services\Ai\Dto\AiModel;
use App\Services\Ai\Dto\StreamEvent;
use Generator;

interface AiDriver
{
    public function name(): string;

    /**
     * Whether the driver has everything it needs to make a request. False
     * means "no credentials", not "the provider is down".
     */
    public function configured(): bool;

    /**
     * @return array<string, AiModel> keyed by model id
     */
    public function models(): array;

    public function model(string $id): ?AiModel;

    /**
     * Stream one completion, running any tool the model calls and continuing
     * until it answers in text or the tool depth is exhausted.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, AiTool>  $tools
     * @param  array<string, mixed>  $options  max_tokens, temperature, json
     * @return Generator<int, StreamEvent>
     */
    public function stream(string $model, array $messages, array $tools = [], array $options = []): Generator;
}
