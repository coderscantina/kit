<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\AuditEntry;
use App\Services\Audit\AuditLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Records every create, update, delete and restore of the model in
 * `audit_entries`, in the same transaction as the write. The entry is also
 * what webhooks are sent from, so a model is either both or neither.
 *
 * The key, the timestamps, `deleted_at` and `version` are never recorded.
 * Hidden and encrypted attributes are recorded as changed, without values.
 * Override auditExclude() to leave out a column that changes on its own
 * (a last-seen timestamp) and would bury the edits people care about.
 *
 * Model events are the trigger, so a write that bypasses them (a query
 * builder update, raw SQL) is not audited. That is the same boundary the
 * reactive layer has.
 *
 * @phpstan-ignore trait.unused
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (self $model) => app(AuditLog::class)->record($model, AuditEntry::CREATED, $model->auditExclude()));
        static::updated(fn (self $model) => app(AuditLog::class)->record($model, AuditEntry::UPDATED, $model->auditExclude()));
        static::deleted(fn (self $model) => app(AuditLog::class)->record($model, AuditEntry::DELETED, $model->auditExclude()));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn (self $model) => app(AuditLog::class)->record($model, AuditEntry::RESTORED, $model->auditExclude()));
        }
    }

    /**
     * Columns left out of the trail entirely.
     *
     * @return array<int, string>
     */
    public function auditExclude(): array
    {
        return [];
    }

    /**
     * @return MorphMany<AuditEntry, $this>
     */
    public function auditEntries(): MorphMany
    {
        return $this->morphMany(AuditEntry::class, 'subject');
    }
}
