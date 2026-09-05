<?php

declare(strict_types=1);

namespace App\Services\Ai\Support;

use App\Services\Ai\Dto\StreamEvent;
use App\Services\Ai\Exceptions\AiException;
use Closure;
use Generator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The Server-Sent-Events transport every AI endpoint answers through.
 *
 * It owns the parts that are easy to get subtly wrong once and then copy:
 * output buffering, keep-alive comments through a proxy that would otherwise
 * time the connection out, and the disconnect check. That last one is the
 * expensive one: without it a closed tab leaves us reading, and paying for,
 * an upstream stream nobody will ever see.
 *
 * An AiException is user-facing and goes out as it is. Anything else is
 * logged with a reference and reported as a generic failure carrying that
 * reference, so a stack trace never reaches a browser.
 */
final class AiSseStream
{
    /** Seconds of silence before a keep-alive comment goes out. */
    private const int PING_INTERVAL = 15;

    /**
     * @param  Closure(): Generator<int, StreamEvent>  $events
     * @param  array<string, mixed>  $context  attached to the error log
     */
    public static function response(Closure $events, array $context = []): StreamedResponse
    {
        return new StreamedResponse(static function () use ($events, $context): void {
            @ignore_user_abort(true);
            @set_time_limit(0);

            // Under FPM there is usually a buffer already, and flushing it is
            // what gets a token to the browser. Only one we opened ourselves
            // may be closed at the end.
            $ownsBuffer = ob_get_level() === 0;

            if ($ownsBuffer) {
                ob_start();
            }

            $write = static function (string $payload): void {
                echo $payload;

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };

            // Opens the stream on the client before the model has said
            // anything, so `fetch` resolves and the UI can show progress.
            $write(": open\n\n");
            $lastWrite = time();

            try {
                foreach ($events() as $event) {
                    if (connection_aborted()) {
                        break;
                    }

                    if ((time() - $lastWrite) >= self::PING_INTERVAL) {
                        $write(": ping\n\n");
                    }

                    $write($event->toSseLine()."\n\n");
                    $lastWrite = time();

                    if ($event->type->isTerminal()) {
                        break;
                    }
                }
            } catch (AiException $e) {
                if (! connection_aborted()) {
                    $write(StreamEvent::error($e->getMessage(), $e->reason)->toSseLine()."\n\n");
                }
            } catch (Throwable $e) {
                $reference = (string) Str::uuid();

                Log::error('AI stream failed', [...$context, 'reference' => $reference, 'exception' => $e]);

                if (! connection_aborted()) {
                    $write(StreamEvent::error("Something went wrong while generating. Reference: {$reference}")->toSseLine()."\n\n");
                }
            }

            if ($ownsBuffer) {
                ob_end_flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            // nginx and friends buffer a response body by default, which
            // holds every token back until the stream ends.
            'X-Accel-Buffering' => 'no',
            'Connection' => 'close',
        ]);
    }
}
