<?php

declare(strict_types=1);

namespace App\Services\Ai\Support;

use Generator;
use Psr\Http\Message\StreamInterface;

/**
 * Turns a chunked `text/event-stream` body into decoded `data:` payloads.
 *
 * A chunk boundary lands wherever the network put it, which is regularly in
 * the middle of a JSON object, so bytes are buffered until a blank line
 * closes the frame. Comment lines (`: keep-alive`) and the `[DONE]` sentinel
 * are dropped; a frame that is not valid JSON is skipped rather than fatal,
 * because one malformed frame should not lose the tokens after it.
 */
final class SseParser
{
    /**
     * @return Generator<int, array<string, mixed>>
     */
    public static function parse(StreamInterface $body): Generator
    {
        $buffer = '';

        while (! $body->eof()) {
            $chunk = $body->read(8192);

            if ($chunk === '') {
                continue;
            }

            $buffer .= $chunk;

            // \r\n\r\n as well as \n\n: the spec allows both and some proxies
            // rewrite one into the other.
            while (($break = self::firstFrameBreak($buffer)) !== null) {
                [$offset, $length] = $break;
                $frame = substr($buffer, 0, $offset);
                $buffer = substr($buffer, $offset + $length);

                $payload = self::decodeFrame($frame);

                if ($payload !== null) {
                    yield $payload;
                }
            }
        }

        $payload = self::decodeFrame($buffer);

        if ($payload !== null) {
            yield $payload;
        }
    }

    /**
     * @return array{0: int, 1: int}|null offset of the break and its length
     */
    private static function firstFrameBreak(string $buffer): ?array
    {
        $candidates = [];

        foreach (["\r\n\r\n" => 4, "\n\n" => 2] as $needle => $length) {
            $offset = strpos($buffer, (string) $needle);

            if ($offset !== false) {
                $candidates[$offset] = $length;
            }
        }

        if ($candidates === []) {
            return null;
        }

        $offset = min(array_keys($candidates));

        return [$offset, $candidates[$offset]];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeFrame(string $frame): ?array
    {
        $data = '';

        foreach (preg_split('/\r?\n/', trim($frame)) ?: [] as $line) {
            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            // Multiple data lines in one frame concatenate, per the spec.
            $data .= ltrim(substr($line, 5), ' ');
        }

        if ($data === '' || $data === '[DONE]') {
            return null;
        }

        $decoded = json_decode($data, true);

        return is_array($decoded) ? $decoded : null;
    }
}
