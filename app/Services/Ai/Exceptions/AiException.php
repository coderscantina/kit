<?php

declare(strict_types=1);

namespace App\Services\Ai\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A failure the user is allowed to see, carrying a machine-readable reason
 * the client can branch on instead of matching English.
 *
 * Provider detail never goes in here. Anything unexpected is logged with a
 * reference and surfaced generically; this is for the handful of cases where
 * the honest answer is short and actionable.
 */
final class AiException extends RuntimeException
{
    public const string REASON_NOT_CONFIGURED = 'not_configured';

    public const string REASON_UNKNOWN_ACTION = 'unknown_action';

    public const string REASON_UNKNOWN_MODEL = 'unknown_model';

    public const string REASON_PROVIDER_UNAVAILABLE = 'provider_unavailable';

    public const string REASON_NO_RESULT = 'no_result';

    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function notConfigured(): self
    {
        return new self(
            self::REASON_NOT_CONFIGURED,
            'AI is not configured on this installation.',
            503,
        );
    }

    public static function unknownAction(string $name): self
    {
        return new self(
            self::REASON_UNKNOWN_ACTION,
            "No AI action is registered as '{$name}'.",
            404,
        );
    }

    public static function unknownModel(string $model): self
    {
        return new self(
            self::REASON_UNKNOWN_MODEL,
            "The configured model '{$model}' is not offered by the provider.",
            422,
        );
    }

    public static function providerUnavailable(): self
    {
        return new self(
            self::REASON_PROVIDER_UNAVAILABLE,
            'The AI provider is unavailable. Please try again in a moment.',
            503,
        );
    }

    public static function noResult(): self
    {
        return new self(
            self::REASON_NO_RESULT,
            'The model returned nothing usable. Please try again.',
            422,
        );
    }

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage(), 'reason' => $this->reason], $this->status);
    }
}
