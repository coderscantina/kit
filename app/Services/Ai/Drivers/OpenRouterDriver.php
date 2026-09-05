<?php

declare(strict_types=1);

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\AiDriver;
use App\Services\Ai\Contracts\AiTool;
use App\Services\Ai\Dto\AiModel;
use App\Services\Ai\Dto\AiUsage;
use App\Services\Ai\Dto\StreamEvent;
use App\Services\Ai\ModelCatalog;
use App\Services\Ai\Support\SseParser;
use Generator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * OpenRouter over its OpenAI-compatible chat completions API.
 *
 * Spoken to with Laravel's HTTP client and {@see SseParser} rather than a
 * provider SDK: the wire format is a handful of JSON frames, and owning the
 * parse keeps the timeouts, the failure logging and the test seam ours.
 */
final class OpenRouterDriver implements AiDriver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly ModelCatalog $catalog,
    ) {}

    public function name(): string
    {
        return 'openrouter';
    }

    public function configured(): bool
    {
        return filled($this->config['api_key'] ?? null);
    }

    /**
     * @return array<string, AiModel>
     */
    public function models(): array
    {
        return $this->catalog->all();
    }

    public function model(string $id): ?AiModel
    {
        return $this->catalog->find($id);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, AiTool>  $tools
     * @param  array<string, mixed>  $options
     * @return Generator<int, StreamEvent>
     */
    public function stream(string $model, array $messages, array $tools = [], array $options = []): Generator
    {
        $depth = 0;
        $maxDepth = (int) config('ai.limits.max_tool_depth', 4);
        $answer = '';
        $usage = null;

        // A loop rather than recursion: a tool round is a continuation of the
        // same answer, and the accumulated text has to survive it.
        while (true) {
            $turn = null;

            try {
                $response = Http::withHeaders($this->headers())
                    ->connectTimeout((int) ($this->config['connect_timeout'] ?? 10))
                    ->timeout((int) ($this->config['stream_idle_timeout'] ?? 120))
                    ->withOptions([
                        'stream' => true,
                        // Applies between chunks, so a model that thinks for a
                        // minute before its first token is fine and a socket
                        // that dies mid-answer is not.
                        'read_timeout' => (int) ($this->config['stream_idle_timeout'] ?? 120),
                    ])
                    ->post($this->endpoint(), $this->payload($model, $messages, $tools, $options));

                if (! $response->successful()) {
                    yield $this->failure(
                        'The AI provider returned an error.',
                        ['model' => $model, 'status' => $response->status(), 'body' => Str::limit($response->body(), 500)],
                    );

                    return;
                }

                $turn = yield from $this->consume($response->toPsrResponse()->getBody());
            } catch (Throwable $e) {
                yield $this->failure('The AI provider could not be reached.', ['model' => $model, 'exception' => $e]);

                return;
            }

            $answer .= $turn['content'];
            $usage = $turn['usage'] ?? $usage;

            if ($turn['toolCalls'] === []) {
                yield StreamEvent::done($answer, ['usage' => $usage?->toArray()]);

                return;
            }

            if (++$depth > $maxDepth) {
                yield $this->failure(
                    'The assistant kept asking for more information and was stopped.',
                    ['model' => $model, 'depth' => $depth],
                );

                return;
            }

            // The assistant turn that requested the calls has to go back in
            // alongside the results, or the provider rejects the follow-up.
            $messages[] = ['role' => 'assistant', 'content' => $turn['content'] ?: null, 'tool_calls' => $turn['toolCalls']];

            foreach ($turn['toolCalls'] as $call) {
                $name = (string) $call['function']['name'];
                $tool = $tools[$name] ?? null;

                if ($tool === null) {
                    yield $this->failure('The assistant asked for a tool that does not exist.', ['tool' => $name]);

                    return;
                }

                yield StreamEvent::status($tool->status());

                try {
                    /** @var array<string, mixed> $input */
                    $input = json_decode((string) $call['function']['arguments'], true) ?: [];
                    $result = $tool->execute($input);
                } catch (Throwable $e) {
                    yield $this->failure('A tool the assistant used failed.', ['tool' => $name, 'exception' => $e]);

                    return;
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) $call['id'],
                    'content' => json_encode($result, JSON_UNESCAPED_UNICODE) ?: '{}',
                ];
            }
        }
    }

    /**
     * Read one response body to its end, yielding deltas as they arrive.
     *
     * @return Generator<int, StreamEvent, mixed, array{content: string, toolCalls: array<int, array<string, mixed>>, usage: AiUsage|null}>
     */
    private function consume(mixed $body): Generator
    {
        $content = '';
        $usage = null;
        /** @var array<int, array<string, mixed>> $calls */
        $calls = [];

        foreach (SseParser::parse($body) as $frame) {
            if (isset($frame['usage']) && is_array($frame['usage'])) {
                $usage = AiUsage::fromPayload((string) ($frame['model'] ?? ''), $frame['usage']);
            }

            $delta = data_get($frame, 'choices.0.delta');

            if (! is_array($delta)) {
                continue;
            }

            // Reasoning tokens are progress, not answer: shown while they
            // arrive, never appended to the result.
            if (filled($delta['reasoning'] ?? null)) {
                yield StreamEvent::status((string) $delta['reasoning']);
            }

            if (filled($delta['content'] ?? null)) {
                $content .= (string) $delta['content'];
                yield StreamEvent::delta((string) $delta['content']);
            }

            if (is_array($delta['tool_calls'] ?? null)) {
                $calls = $this->mergeToolCalls($calls, $delta['tool_calls']);
            }
        }

        return ['content' => $content, 'toolCalls' => array_values($calls), 'usage' => $usage];
    }

    /**
     * Tool calls arrive in fragments keyed by index: the name in one frame,
     * the arguments a few characters at a time after it.
     *
     * @param  array<int, array<string, mixed>>  $calls
     * @param  array<int, array<string, mixed>>  $fragments
     * @return array<int, array<string, mixed>>
     */
    private function mergeToolCalls(array $calls, array $fragments): array
    {
        foreach ($fragments as $fragment) {
            $index = (int) ($fragment['index'] ?? 0);

            $calls[$index] ??= [
                'id' => 'call_'.Str::random(8),
                'type' => 'function',
                'function' => ['name' => '', 'arguments' => ''],
            ];

            if (filled($fragment['id'] ?? null)) {
                $calls[$index]['id'] = (string) $fragment['id'];
            }

            if (filled(data_get($fragment, 'function.name'))) {
                $calls[$index]['function']['name'] = (string) data_get($fragment, 'function.name');
            }

            if (filled(data_get($fragment, 'function.arguments'))) {
                $calls[$index]['function']['arguments'] .= (string) data_get($fragment, 'function.arguments');
            }
        }

        ksort($calls);

        return $calls;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, AiTool>  $tools
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function payload(string $model, array $messages, array $tools, array $options): array
    {
        $catalogued = $this->model($model);

        $payload = [
            'model' => $model,
            // The system message goes first and stays byte-identical between
            // requests, so provider-side prompt caching can hit on it.
            'messages' => $messages,
            'stream' => true,
            'usage' => ['include' => true],
            'max_tokens' => max(1, (int) ($options['max_tokens'] ?? config('ai.defaults.max_tokens'))),
            'temperature' => (float) ($options['temperature'] ?? config('ai.defaults.temperature')),
        ];

        // An unknown model is assumed not to take tools: OpenRouter's long
        // tail mostly does not, and a rejected request loses the whole turn.
        if ($tools !== [] && $catalogued !== null && $catalogued->supportsTools) {
            $payload['tools'] = array_values(array_map(
                static fn (AiTool $tool): array => [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool->name(),
                        'description' => $tool->description(),
                        'parameters' => $tool->schema(),
                    ],
                ],
                $tools,
            ));
        }

        if (($options['json'] ?? false) && $catalogued !== null && $catalogued->supportsJsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        return $payload;
    }

    private function endpoint(): string
    {
        return rtrim((string) $this->config['base_url'], '/').'/chat/completions';
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.((string) $this->config['api_key']),
            'Content-Type' => 'application/json',
            'Accept' => 'text/event-stream',
            'HTTP-Referer' => (string) ($this->config['site_url'] ?: config('app.url')),
            'X-Title' => (string) ($this->config['site_name'] ?: config('app.name')),
        ];
    }

    /**
     * Log what actually happened and hand the user a reference for it.
     *
     * @param  array<string, mixed>  $context
     */
    private function failure(string $message, array $context): StreamEvent
    {
        $reference = (string) Str::uuid();

        Log::error('AI driver error', [...$context, 'driver' => $this->name(), 'reference' => $reference]);

        return StreamEvent::error("{$message} (reference: {$reference})", 'provider_error');
    }
}
