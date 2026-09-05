<?php

declare(strict_types=1);

namespace App\Services\Ai\Dto;

/**
 * One frame of an AI stream, in the shape the client's `useAiStream` reads.
 *
 * `status` is progress the user may see but should not keep (a tool running,
 * a model reasoning). `delta` is the answer arriving. `done` carries the full
 * text once, so a client that missed a delta still ends up correct.
 */
final readonly class StreamEvent
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        public StreamEventType $type,
        public ?string $message = null,
        public ?string $content = null,
        public ?array $data = null,
    ) {}

    public static function status(string $message): self
    {
        return new self(StreamEventType::Status, message: $message);
    }

    public static function delta(string $content): self
    {
        return new self(StreamEventType::Delta, content: $content);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function done(string $content, ?array $data = null): self
    {
        return new self(StreamEventType::Done, content: $content, data: $data);
    }

    public static function error(string $message, ?string $reason = null): self
    {
        return new self(
            StreamEventType::Error,
            message: $message,
            data: $reason !== null ? ['reason' => $reason] : null,
        );
    }

    /**
     * The `data:` line of the SSE frame. Keys the client does not need for
     * this type are left out rather than sent as null.
     */
    public function toSseLine(): string
    {
        $payload = match ($this->type) {
            StreamEventType::Status => ['type' => $this->type->value, 'message' => $this->message],
            StreamEventType::Delta => ['type' => $this->type->value, 'content' => $this->content],
            StreamEventType::Done => ['type' => $this->type->value, 'content' => $this->content, 'data' => $this->data],
            StreamEventType::Error => array_filter([
                'type' => $this->type->value,
                'message' => $this->message,
                'reason' => $this->data['reason'] ?? null,
            ], static fn (mixed $value): bool => $value !== null),
        };

        return 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
