<?php

declare(strict_types=1);

namespace App\Services\Ai\Testing;

use App\Services\Ai\Contracts\AiDriver;
use App\Services\Ai\Contracts\AiTool;
use App\Services\Ai\Dto\AiModel;
use App\Services\Ai\Dto\AiUsage;
use App\Services\Ai\Dto\StreamEvent;
use Generator;

/**
 * A scripted driver for tests, so an action can be exercised end to end
 * without a network, a key or a bill.
 *
 *     $driver = FakeAiDriver::swap('Hello ', 'world');
 *     $this->postJson('/api/ai/stream', [...]);
 *     $this->assertStringContainsString('summarise', $driver->systemPrompt());
 *
 * What it records is the half that matters in a test: the model that was
 * asked, the messages that were sent, and whether the user's data ended up
 * inside the prompt at all.
 */
final class FakeAiDriver implements AiDriver
{
    /** @var array<int, string> */
    private array $chunks = ['ok'];

    /** @var array{name: string, input: array<string, mixed>}|null */
    private ?array $toolCall = null;

    private bool $configured = true;

    /** @var array<int, array{model: string, messages: array<int, array<string, mixed>>, tools: array<int, string>, options: array<string, mixed>}> */
    public array $calls = [];

    /** @var array<int, mixed> */
    public array $toolResults = [];

    /**
     * Bind this driver in place of the real one for the rest of the test.
     */
    public static function swap(string ...$chunks): self
    {
        $fake = new self;

        if ($chunks !== []) {
            $fake->chunks = $chunks;
        }

        app()->instance(AiDriver::class, $fake);

        return $fake;
    }

    /**
     * Make the next stream call the named tool before it answers, the way a
     * real model would.
     *
     * @param  array<string, mixed>  $input
     */
    public function callsTool(string $name, array $input = []): self
    {
        $this->toolCall = ['name' => $name, 'input' => $input];

        return $this;
    }

    public function unconfigured(): self
    {
        $this->configured = false;

        return $this;
    }

    public function name(): string
    {
        return 'fake';
    }

    public function configured(): bool
    {
        return $this->configured;
    }

    /**
     * @return array<string, AiModel>
     */
    public function models(): array
    {
        return ['fake/model' => $this->model('fake/model')];
    }

    public function model(string $id): AiModel
    {
        return new AiModel(
            id: $id,
            name: $id,
            capabilities: ['text', 'tools'],
            supportsTools: true,
            supportsJsonMode: true,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, AiTool>  $tools
     * @param  array<string, mixed>  $options
     * @return Generator<int, StreamEvent>
     */
    public function stream(string $model, array $messages, array $tools = [], array $options = []): Generator
    {
        $this->calls[] = [
            'model' => $model,
            'messages' => $messages,
            'tools' => array_keys($tools),
            'options' => $options,
        ];

        if ($this->toolCall !== null && isset($tools[$this->toolCall['name']])) {
            $tool = $tools[$this->toolCall['name']];

            yield StreamEvent::status($tool->status());

            $this->toolResults[] = $tool->execute($this->toolCall['input']);
        }

        foreach ($this->chunks as $chunk) {
            yield StreamEvent::delta($chunk);
        }

        yield StreamEvent::done(
            implode('', $this->chunks),
            ['usage' => (new AiUsage($model, 10, 20, 0.001))->toArray()],
        );
    }

    /**
     * The system message of the last call, for asserting what the model was
     * told about.
     */
    public function systemPrompt(int $call = 0): string
    {
        return (string) ($this->calls[$call]['messages'][0]['content'] ?? '');
    }

    /** The user message of the last call. */
    public function userPrompt(int $call = 0): string
    {
        return (string) ($this->calls[$call]['messages'][1]['content'] ?? '');
    }
}
