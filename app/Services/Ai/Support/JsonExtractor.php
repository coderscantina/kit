<?php

declare(strict_types=1);

namespace App\Services\Ai\Support;

/**
 * Tolerant decoder for JSON a model produced.
 *
 * Models wrap JSON in ```json fences and pad it with an apology even when
 * asked not to. This handles the clean payload, the fenced payload and the
 * payload buried in prose, and returns null when there is nothing to parse,
 * so a caller can fall back rather than crash on a chatty model.
 *
 * @phpstan-type Decoded array<mixed>|scalar|null
 */
final class JsonExtractor
{
    public static function decode(?string $raw): mixed
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $text = self::stripFences(trim($raw));

        $decoded = json_decode($text, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        $candidate = self::firstBalanced($text);

        if ($candidate === null) {
            return null;
        }

        $decoded = json_decode($candidate, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    public static function stripFences(string $text): string
    {
        $text = preg_replace('/^\s*```(?:json|javascript|js)?\s*\n?/i', '', $text) ?? $text;
        $text = preg_replace('/\n?```\s*$/', '', $text) ?? $text;

        return trim($text);
    }

    /**
     * The first balanced object or array in arbitrary text. Strings and their
     * escapes are tracked, so a brace inside a value does not close the object.
     */
    private static function firstBalanced(string $text): ?string
    {
        $start = null;
        $open = '{';
        $close = '}';
        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            if ($text[$i] === '{' || $text[$i] === '[') {
                $start = $i;
                $open = $text[$i];
                $close = $open === '{' ? '}' : ']';
                break;
            }
        }

        if ($start === null) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escaped = false;

        for ($i = $start; $i < $length; $i++) {
            $char = $text[$i];

            if ($inString) {
                // Order matters: a quote that follows a backslash is content,
                // not the end of the string.
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;

                continue;
            }

            if ($char === $open) {
                $depth++;
            } elseif ($char === $close && --$depth === 0) {
                return substr($text, $start, $i - $start + 1);
            }
        }

        return null;
    }
}
