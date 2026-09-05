<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Services\Ai\Contracts\AiTool;
use App\Services\Ai\Drivers\OpenRouterDriver;
use App\Services\Ai\Dto\StreamEvent;
use App\Services\Ai\Dto\StreamEventType;
use App\Services\Ai\ModelCatalog;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(OpenRouterDriver::class)]
#[CoversClass(ModelCatalog::class)]
final class OpenRouterDriverTest extends TestCase
{
    private const string MODEL = 'test/model';

    #[Test]
    public function it_streams_deltas_and_ends_with_the_full_answer_and_usage(): void
    {
        $this->fakeProvider([$this->sse([
            $this->contentChunk('Hello'),
            $this->contentChunk(' world'),
            ['model' => self::MODEL, 'usage' => ['prompt_tokens' => 11, 'completion_tokens' => 4, 'cost' => 0.002]],
        ])]);

        $events = $this->collect();

        $this->assertSame(['Hello', ' world'], $this->contentsOf($events, StreamEventType::Delta));

        $done = $events[count($events) - 1];
        $this->assertSame(StreamEventType::Done, $done->type);
        $this->assertSame('Hello world', $done->content);
        $this->assertSame(15, $done->data['usage']['totalTokens']);
        $this->assertSame(0.002, $done->data['usage']['cost']);
    }

    #[Test]
    public function it_runs_a_tool_the_model_asks_for_and_streams_the_answer_that_follows(): void
    {
        $this->fakeProvider([
            $this->sse([$this->toolCallChunk('lookup', '{"id":7}')]),
            $this->sse([$this->contentChunk('Seven it is')]),
        ]);

        $tool = new class implements AiTool
        {
            /** @var array<string, mixed>|null */
            public ?array $received = null;

            public function name(): string
            {
                return 'lookup';
            }

            public function description(): string
            {
                return 'Looks a thing up';
            }

            public function schema(): array
            {
                return ['type' => 'object', 'properties' => []];
            }

            public function status(): string
            {
                return 'Looking it up...';
            }

            public function execute(array $input): mixed
            {
                $this->received = $input;

                return ['answer' => 7];
            }
        };

        $events = $this->collect(['lookup' => $tool]);

        $this->assertSame(['id' => 7], $tool->received);
        $this->assertSame(['Looking it up...'], array_map(
            fn (StreamEvent $event): string => (string) $event->message,
            array_values(array_filter($events, fn (StreamEvent $e): bool => $e->type === StreamEventType::Status)),
        ));
        $this->assertSame('Seven it is', $events[count($events) - 1]->content);

        // The tool result has to travel back in a `tool` message, or the
        // provider rejects the follow-up as an orphaned tool call.
        $followUp = Http::recorded()[2][0];
        $messages = $followUp->data()['messages'];
        $this->assertSame('assistant', $messages[1]['role']);
        $this->assertSame('tool', $messages[2]['role']);
        $this->assertSame('{"answer":7}', $messages[2]['content']);
    }

    #[Test]
    public function a_model_that_does_not_advertise_tools_is_not_sent_any(): void
    {
        $this->fakeProvider([$this->sse([$this->contentChunk('fine')])], tools: false);

        $this->collect(['lookup' => $this->createStub(AiTool::class)]);

        $this->assertArrayNotHasKey('tools', Http::recorded()[1][0]->data());
    }

    #[Test]
    public function a_provider_error_becomes_one_error_event_and_never_leaks_the_body(): void
    {
        Http::fake([
            '*/models' => Http::response(['data' => []]),
            '*/chat/completions' => Http::response('{"error":{"message":"invalid key sk-secret"}}', 401),
        ]);

        $events = $this->collect();

        $this->assertCount(1, $events);
        $this->assertSame(StreamEventType::Error, $events[0]->type);
        $this->assertStringNotContainsString('sk-secret', (string) $events[0]->message);
        $this->assertStringContainsString('reference:', (string) $events[0]->message);
    }

    /**
     * @param  array<string, AiTool>  $tools
     * @return array<int, StreamEvent>
     */
    private function collect(array $tools = []): array
    {
        $driver = new OpenRouterDriver(
            ['api_key' => 'test-key', 'base_url' => 'https://openrouter.test/api/v1', 'site_url' => '', 'site_name' => ''],
            app(ModelCatalog::class),
        );

        // preserve_keys: false — a `yield from` restarts the inner
        // generator's keys, so the outer events would overwrite them.
        return iterator_to_array(
            $driver->stream(self::MODEL, [['role' => 'user', 'content' => 'hi']], $tools),
            preserve_keys: false,
        );
    }

    /**
     * @param  array<int, string>  $completions
     */
    private function fakeProvider(array $completions, bool $tools = true): void
    {
        $sequence = Http::sequence();

        foreach ($completions as $completion) {
            $sequence->push($completion);
        }

        Http::fake([
            '*/models' => Http::response(['data' => [[
                'id' => self::MODEL,
                'name' => 'Test model',
                'context_length' => 128000,
                'pricing' => ['prompt' => '0.000001', 'completion' => '0.000002'],
                'supported_parameters' => $tools ? ['tools', 'response_format'] : [],
            ]]]),
            '*/chat/completions' => $sequence,
        ]);

        $this->app->forgetInstance(ModelCatalog::class);
        $this->app->instance(ModelCatalog::class, new ModelCatalog([
            'api_key' => 'test-key',
            'base_url' => 'https://openrouter.test/api/v1',
        ]));
    }

    /**
     * @param  array<int, array<string, mixed>>  $chunks
     */
    private function sse(array $chunks): string
    {
        $body = '';

        foreach ($chunks as $chunk) {
            $body .= 'data: '.json_encode($chunk)."\n\n";
        }

        return $body."data: [DONE]\n\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function contentChunk(string $content): array
    {
        return ['choices' => [['delta' => ['content' => $content]]]];
    }

    /**
     * @return array<string, mixed>
     */
    private function toolCallChunk(string $name, string $arguments): array
    {
        return ['choices' => [[
            'delta' => ['tool_calls' => [[
                'index' => 0,
                'id' => 'call_1',
                'function' => ['name' => $name, 'arguments' => $arguments],
            ]]],
            'finish_reason' => 'tool_calls',
        ]]];
    }

    /**
     * @param  array<int, StreamEvent>  $events
     * @return array<int, string>
     */
    private function contentsOf(array $events, StreamEventType $type): array
    {
        return array_values(array_map(
            fn (StreamEvent $event): string => (string) $event->content,
            array_filter($events, fn (StreamEvent $event): bool => $event->type === $type),
        ));
    }
}
