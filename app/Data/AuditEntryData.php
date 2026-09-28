<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\AuditEntry;
use App\Services\Account\AvatarStorage;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One line of a record's history. A null actor is the system: a queue
 * worker, a console command, or an account that has since been deleted.
 */
#[TypeScript]
final class AuditEntryData extends Data
{
    /**
     * @param  array<int, AuditChangeData>  $changes
     */
    public function __construct(
        public string $id,
        /** `created`, `updated`, `deleted` or `restored`. */
        public string $event,
        public ?string $actorName,
        public ?string $actorAvatarUrl,
        public ?string $impersonatorName,
        public array $changes,
        public string $createdAt,
    ) {}

    public static function fromModel(AuditEntry $entry): self
    {
        return new self(
            id: $entry->id,
            event: $entry->event,
            actorName: $entry->actor?->name,
            actorAvatarUrl: $entry->actor === null ? null : AvatarStorage::url($entry->actor),
            impersonatorName: $entry->impersonator?->name,
            changes: array_map(
                AuditChangeData::fromChange(...),
                array_keys($entry->changes),
                array_values($entry->changes),
            ),
            createdAt: $entry->created_at->toIso8601String(),
        );
    }
}
