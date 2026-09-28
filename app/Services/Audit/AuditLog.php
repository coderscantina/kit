<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditEntry;
use App\Services\Auth\ImpersonationService;
use App\Services\Webhooks\WebhookFanOut;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Stringable;
use UnitEnum;

/**
 * Turns a model event into an audit entry. The actor is whoever the request
 * is authenticated as, a session or an access token; a queue worker or a
 * console command records none, which the history shows as the system.
 * Under impersonation both people are kept.
 */
class AuditLog
{
    /** A long text field is a diff in the history, not an archive of it. */
    private const int MAX_STRING_LENGTH = 1000;

    public function __construct(
        private readonly ImpersonationService $impersonation,
        private readonly WebhookFanOut $webhooks,
    ) {}

    /**
     * @param  array<int, string>  $excluded  the model's auditExclude()
     */
    public function record(Model $subject, string $event, array $excluded = []): void
    {
        $changes = $this->changes($subject, $event, $excluded);

        // Only excluded columns moved, or a restore's own save.
        if ($event === AuditEntry::UPDATED && $changes === []) {
            return;
        }

        $entry = AuditEntry::query()->create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (string) $subject->getKey(),
            'event' => $event,
            'actor_id' => Auth::id(),
            'impersonator_id' => $this->impersonatorId(),
            'changes' => $changes,
            'created_at' => now(),
        ]);

        $this->webhooks->dispatch($subject, $entry);
    }

    /**
     * @param  array<int, string>  $excluded
     * @return array<string, array{before?: mixed, after?: mixed, redacted?: true}>
     */
    private function changes(Model $subject, string $event, array $excluded): array
    {
        $fields = match ($event) {
            AuditEntry::UPDATED => array_keys($subject->getChanges()),
            default => array_keys($subject->getAttributes()),
        };

        $skipped = [
            $subject->getKeyName(), 'created_at', 'updated_at', 'deleted_at', 'version',
            ...$excluded,
        ];
        $redacted = [...$subject->getHidden(), ...$this->encrypted($subject)];

        $changes = [];

        foreach (array_diff($fields, $skipped) as $field) {
            // A create or a delete lists the row as it was; its empty columns
            // are not something that happened.
            if ($event !== AuditEntry::UPDATED && $subject->getAttributes()[$field] === null) {
                continue;
            }

            if (in_array($field, $redacted, true)) {
                $changes[$field] = ['redacted' => true];

                continue;
            }

            $changes[$field] = match ($event) {
                AuditEntry::CREATED => ['after' => $this->value($subject->getAttribute($field))],
                AuditEntry::DELETED => ['before' => $this->value($subject->getAttribute($field))],
                AuditEntry::RESTORED => ['after' => $this->value($subject->getAttribute($field))],
                default => [
                    'before' => $this->value($subject->getOriginal($field)),
                    'after' => $this->value($subject->getAttribute($field)),
                ],
            };
        }

        return $changes;
    }

    /**
     * An encrypted cast decrypts on read, so reading it for the trail would
     * store the plaintext the cast exists to keep off disk.
     *
     * @return array<int, string>
     */
    private function encrypted(Model $subject): array
    {
        return array_keys(array_filter(
            $subject->getCasts(),
            fn (string $cast): bool => str_starts_with($cast, 'encrypted'),
        ));
    }

    /** What a JSON column can hold, and what a reader of the history can read. */
    private function value(mixed $value): mixed
    {
        return match (true) {
            $value === null, is_bool($value), is_int($value), is_float($value) => $value,
            is_string($value) => Str::limit($value, self::MAX_STRING_LENGTH),
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof Arrayable => $value->toArray(),
            is_array($value) => $value,
            $value instanceof Stringable => Str::limit((string) $value, self::MAX_STRING_LENGTH),
            default => null,
        };
    }

    private function impersonatorId(): ?string
    {
        $request = request();

        return $request->hasSession() ? $this->impersonation->impersonatorId($request->session()) : null;
    }
}
