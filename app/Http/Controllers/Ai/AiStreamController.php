<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Services\Ai\Runtime\AiRunner;
use App\Services\Ai\Support\AiSseStream;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The one AI endpoint. Which action runs is a name in the payload, the same
 * way /rq/subscribe takes a query name, so a new AI feature is a class and
 * not another controller.
 *
 * `prepare()` runs before the response is constructed, so an unknown action,
 * an invalid payload or a denied caller comes back as a status code the
 * client can read. Once the stream is open the only channel left is an error
 * event.
 */
final class AiStreamController extends Controller
{
    public function __construct(
        private readonly AiRunner $runner,
    ) {}

    public function __invoke(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'max:100'],
            'args' => ['sometimes', 'array'],
        ]);

        /** @var array<string, mixed> $args */
        $args = $request->input('args', []);

        $prepared = $this->runner->prepare((string) $validated['action'], $args, $request->user());

        return AiSseStream::response(
            fn () => $this->runner->stream($prepared),
            ['action' => $prepared->name, 'user' => $prepared->userId],
        );
    }
}
