<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One inbox row as the client renders it.
 *
 * `type` is the stable key, not a class name, and `data` is whatever the
 * notification put there. The client keys its copy off `type`, so a
 * notification written a year ago still renders after the class that produced
 * it has been renamed or deleted.
 *
 * `deliveries` is here because a person asking "why did I get a text about
 * this?" deserves an answer in the interface rather than in a log.
 */
#[TypeScript]
final class NotificationData extends Data
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $deliveries  channel => ISO 8601 timestamp
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $data,
        public NotificationStatus $status,
        public array $deliveries,
        public ?string $seenAt,
        public ?string $archivedAt,
        public string $createdAt,
    ) {}

    public static function fromModel(Notification $notification): self
    {
        return new self(
            id: $notification->id,
            type: $notification->type,
            data: $notification->data,
            status: $notification->status(),
            deliveries: $notification->deliveries,
            seenAt: $notification->read_at?->toIso8601String(),
            archivedAt: $notification->archived_at?->toIso8601String(),
            createdAt: $notification->created_at?->toIso8601String() ?? '',
        );
    }
}
