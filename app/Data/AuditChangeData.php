<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One field of an audit entry. Values arrive as display strings: a string
 * as itself, anything else as its JSON. Null means the side does not exist
 * (a create has no before) or the value was null; `redacted` means the field
 * changed and its values are not shown to anyone.
 */
#[TypeScript]
final class AuditChangeData extends Data
{
    public function __construct(
        public string $field,
        public ?string $before,
        public ?string $after,
        public bool $redacted,
    ) {}

    /**
     * @param  array{before?: mixed, after?: mixed, redacted?: true}  $change
     */
    public static function fromChange(string $field, array $change): self
    {
        return new self(
            field: $field,
            before: self::display($change['before'] ?? null),
            after: self::display($change['after'] ?? null),
            redacted: $change['redacted'] ?? false,
        );
    }

    private static function display(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_string($value) => $value,
            default => (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        };
    }
}
