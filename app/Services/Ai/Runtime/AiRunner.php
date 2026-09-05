<?php

declare(strict_types=1);

namespace App\Services\Ai\Runtime;

use App\Services\Ai\AiAction;
use App\Services\Ai\Contracts\AiDriver;
use App\Services\Ai\Contracts\AiTool;
use App\Services\Ai\Dto\AiUsage;
use App\Services\Ai\Dto\StreamEvent;
use App\Services\Ai\Dto\StreamEventType;
use App\Services\Ai\Events\AiUsageRecorded;
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Registry\AiCatalog;
use App\Services\Ai\Support\JsonExtractor;
use Generator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Data;

/**
 * Runs a named AI action: validate, authorize, prompt, stream.
 *
 * Split in two on purpose. `prepare()` does everything that can still answer
 * with a status code, so a bad payload is a 422 and a denied caller is a 403
 * before a single byte of `text/event-stream` has gone out. `stream()` is
 * what runs inside the response, where the only way to report a problem is
 * an error event.
 */
final class AiRunner
{
    public function __construct(
        private readonly AiCatalog $catalog,
        private readonly AiDriver $driver,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws AiException
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function prepare(string $name, array $payload, Authenticatable $user): PreparedAction
    {
        if (! $this->driver->configured()) {
            throw AiException::notConfigured();
        }

        $class = $this->catalog->action($name);

        if ($class === null) {
            throw AiException::unknownAction($name);
        }

        /** @var AiAction<Data> $action */
        $action = app($class);

        $argsClass = $class::args();
        $args = $argsClass::validateAndCreate($payload);

        $action->authorize($user, $args);

        $id = $user->getAuthIdentifier();

        return new PreparedAction($name, $action, $args, is_numeric($id) ? (int) $id : null);
    }

    /**
     * @return Generator<int, StreamEvent>
     */
    public function stream(PreparedAction $prepared): Generator
    {
        $action = $prepared->action;
        $args = $prepared->args;

        $model = $action->model($args) ?? (string) config('ai.model');

        /** @var array<string, AiTool> $tools */
        $tools = [];

        foreach ($action->tools($args) as $tool) {
            $tools[$tool->name()] = $tool;
        }

        $messages = [
            ['role' => 'system', 'content' => $action->system($args)],
            ['role' => 'user', 'content' => (string) $action->prompt($args)],
        ];

        foreach ($this->driver->stream($model, $messages, $tools, $action->options($args)) as $event) {
            if ($event->type === StreamEventType::Done) {
                $this->recordUsage($prepared, $model, $event);
            }

            yield $event;
        }
    }

    /**
     * Run an action to completion without a client attached, for a job or a
     * command. Returns the full text.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws AiException
     */
    public function generate(string $name, array $payload, Authenticatable $user): string
    {
        $content = '';

        foreach ($this->stream($this->prepare($name, $payload, $user)) as $event) {
            $content = match ($event->type) {
                StreamEventType::Delta => $content.$event->content,
                StreamEventType::Done => (string) $event->content,
                StreamEventType::Error => throw AiException::noResult(),
                default => $content,
            };
        }

        if (trim($content) === '') {
            throw AiException::noResult();
        }

        return $content;
    }

    /**
     * `generate()` for an action that asks for JSON, decoded tolerantly
     * because models fence and preface their JSON however they like.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws AiException
     */
    public function generateJson(string $name, array $payload, Authenticatable $user): mixed
    {
        $decoded = JsonExtractor::decode($this->generate($name, $payload, $user));

        if ($decoded === null) {
            throw AiException::noResult();
        }

        return $decoded;
    }

    private function recordUsage(PreparedAction $prepared, string $model, StreamEvent $event): void
    {
        /** @var array<string, mixed>|null $usage */
        $usage = $event->data['usage'] ?? null;

        if (! is_array($usage)) {
            return;
        }

        AiUsageRecorded::dispatch($prepared->name, $prepared->userId, new AiUsage(
            model: ($usage['model'] ?? '') ?: $model,
            promptTokens: (int) ($usage['promptTokens'] ?? 0),
            completionTokens: (int) ($usage['completionTokens'] ?? 0),
            cost: (float) ($usage['cost'] ?? 0.0),
        ));
    }
}
