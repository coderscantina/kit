<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Base for the REST leftovers (auth, account, invites). Three helpers and
 * nothing else; data goes through reactive queries and mutations.
 */
abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Clamp pagination so a client cannot request a million rows.
     */
    protected function perPage(Request $request, int $default = 20, int $max = 100): int
    {
        $value = (int) $request->input('per_page', $default);

        return max(1, min($value, $max));
    }

    /**
     * Log an unexpected failure under a support-safe id. Exception details
     * never reach the client; the id is what they quote to support.
     *
     * @param  array<string, mixed>  $context
     */
    protected function internalServerError(Throwable $exception, string $message, array $context = []): JsonResponse
    {
        $errorId = (string) Str::uuid();

        Log::error($message, [...$context, 'error_id' => $errorId, 'exception' => $exception]);

        return response()->json(['message' => $message, 'error_id' => $errorId], 500);
    }
}
