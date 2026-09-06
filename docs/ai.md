# AI

Streamed model calls, on the same contract as the rest of the app: a named
class, laravel-data arguments, a policy call, and a client that is typed from
the server.

Provider is OpenRouter. It fronts every major model behind one
OpenAI-compatible API, so one driver covers Claude, GPT, Gemini and the rest,
and switching model is an env change rather than a code change.

## Turning it on

```sh
OPENROUTER_API_KEY=sk-or-...
AI_MODEL=anthropic/claude-sonnet-4.5
```

Without a key the app boots exactly as before: `features.ai` is false, the
assistant is hidden from the sidebar, and `kit:doctor` says so as a warning
rather than a failure. Check a model slug before committing it:

```sh
php artisan ai:models --search=claude --tools
```

<!--@include: ./generated/ai-models.md-->

## An action

An action is one thing you ask a model to do. It lives with the feature that
owns it and is discovered from the attribute, so there is no list to update.

```sh
php artisan make:ai-action posts.summarize
```

<!--@include: ./generated/make-ai-action.md-->

That writes `app/Ai/Actions/SummarizePost.php`, its args class and a
test that runs it against a faked model. Every action lives in
`app/Ai/Actions`, its args class in `app/Data`, and the test in
`tests/Feature/<Feature>/`; `assistant.ask` is the worked example.

```php
/**
 * @extends AiAction<SummarizePostArgs>
 */
#[AiStream('posts.summarize')]
final class SummarizePost extends AiAction
{
    public static function args(): string
    {
        return SummarizePostArgs::class;
    }

    public function authorize(Authenticatable $user, Data $args): void
    {
        $this->gate($user)->authorize('view', Post::findOrFail($args->postId));
    }

    public function system(Data $args): string
    {
        return 'You summarise posts. Two sentences, no preamble.';
    }

    public function prompt(Data $args): Prompt
    {
        return Prompt::make('Summarise the post below.')
            ->with('post', Post::findOrFail($args->postId)->only('title', 'body'));
    }
}
```

The args class appears twice, in `@extends AiAction<SummarizePostArgs>` and in
`args()`, and PHPStan checks the two agree. That is what types `$args->postId`
inside methods whose signature has to stay `Data $args`.

`model()` pins a model when the default is wrong for the job. `options()`
takes `max_tokens`, `temperature` and `json`. `tools()` hands the model
functions it may call.

Run `php artisan types:generate` afterwards and commit the result; CI fails on
a stale `generated.d.ts`.

## The client

```vue
<script setup lang="ts">
import { useAiStream } from '~/composables/useAiStream'

const { content, error, isStreaming, run } = useAiStream('posts.summarize')

const summarise = () => run({ postId })
</script>

<template>
  <button
    :disabled="isStreaming"
    @click="summarise"
  >
    Summarise
  </button>
  <p>{{ content }}</p>
  <p v-if="error">{{ error }}</p>
</template>
```

`content`, `status`, `error`, `reason` and `isStreaming` are refs; `run()`
resolves with the finished text and `cancel()` aborts. Destructure them, as
above, or read them as `summary.content.value` — nested refs on the returned
object are not unwrapped in a template. The name is a literal union off
`Kit.AiMap`, so renaming an action server-side breaks the typecheck rather
than production.

One stream per instance: a second `run()` aborts the first. Create a second
instance for a second concurrent stream. Never fetch `/api/ai/stream`
directly — the CSRF handshake, the abort handling and the terminal-event rules
live in the composable.

## What comes back

Four event types, in one order: any number of `status` and `delta` events,
then exactly one `done` or `error`, and nothing after it.

| Event    | Means                                                       |
| -------- | ----------------------------------------------------------- |
| `status` | progress: a tool running, a model reasoning. Not the answer |
| `delta`  | the next piece of the answer                                |
| `done`   | the whole answer again, plus token counts and cost          |
| `error`  | the stream failed; `reason` says why in machine terms       |

Failures that happen before the stream opens — an unknown action, an invalid
payload, a denied caller, no provider key — are ordinary status codes with a
`reason` in the body, because a client can act on those.

`done` carries usage, and the server dispatches `AiUsageRecorded` with the
action, the user and what it cost. Listen for it if you want a ledger; the kit
does not write one for you.

## Tools

A tool is a function the model may call mid-stream.

```php
final class FindPost implements AiTool
{
    public function name(): string { return 'find_post'; }
    public function description(): string { return 'Finds a post by title'; }
    public function schema(): array { return ['type' => 'object', 'properties' => [...]]; }
    public function status(): string { return 'Searching posts...'; }

    public function execute(array $input): mixed
    {
        // Authorize here. The model chose these arguments.
    }
}
```

Two rules. A tool runs server-side with nobody watching, so it authorizes
every row it touches; an id the model produced is not proof the user may see
it. And each tool round is another billed request, which is why the loop stops
at `AI_MAX_TOOL_DEPTH` (4) rounds.

Tools are only sent to models that advertise support for them. OpenRouter's
long tail mostly does not, and a model that is not in the catalogue is assumed
not to, because a rejected request loses the whole turn.

## Prompt injection

Everything the user or the database supplied goes through `Prompt::with()`,
which fences it in a tag carrying a per-request nonce:

```
<post-3fJk91xQ>
{"title":"…"}
</post-3fJk91xQ>
```

Without the nonce, a payload containing `</post>` closes the block and the
rest of it reads as instructions. Never concatenate user data into the
instruction string.

This is a mitigation, not a boundary. The real boundary is that tools
authorize, and that `handle()`-equivalent code never trusts a model's output
as a permission decision.

## Cost and limits

- `/api/ai/stream` has its own rate limiter, `AI_RATE_LIMIT` (20/minute per
  user), because an AI request costs money and seconds while the rest of the
  API costs neither.
- A closed tab stops the upstream stream. The transport checks
  `connection_aborted()` between frames, so an abandoned answer stops being
  billed instead of running to completion into nowhere.
- The model catalogue is cached for half a day. `php artisan ai:models
--refresh` re-fetches it.
- The system message goes first and carries no per-request data, so the
  provider can cache that prefix across calls.

## Testing

`FakeAiDriver` replaces the provider, so an action is tested without a network
or a bill:

```php
$driver = FakeAiDriver::swap('Two ', 'words');

$this->post('/api/ai/stream', ['action' => 'posts.summarize', 'args' => [...]])
    ->assertOk();

$this->assertStringContainsString('the title', $driver->userPrompt());
```

`callsTool('find_post', [...])` makes the fake call a tool before answering,
the way a real model would. The generated test asserts both halves of the
contract: it streams for someone allowed to run it, and 403s for someone who
is not.

## What this is not

No conversation store, no per-user budgets, no model picker in the UI. Those
are product decisions, and the pieces to build them — the usage event, the
model catalogue, the typed action map — are here.
