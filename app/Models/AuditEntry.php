<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One change to an Auditable row. Append-only: nothing updates an entry, and
 * `model:prune` removes the ones older than `kit.audit.retention_days`.
 *
 * `changes` maps a field to `{before, after}`, or to `{redacted: true}` for
 * a hidden or encrypted one. A create has no `before`, a delete no `after`.
 *
 * @property string $id
 * @property string $subject_type
 * @property string $subject_id
 * @property string $event
 * @property string|null $actor_id
 * @property string|null $impersonator_id
 * @property array<string, array{before?: mixed, after?: mixed, redacted?: true}> $changes
 * @property Carbon $created_at
 */
#[Fillable(['subject_type', 'subject_id', 'event', 'actor_id', 'impersonator_id', 'changes', 'created_at'])]
class AuditEntry extends Model
{
    use MassPrunable;

    public const string CREATED = 'created';

    public const string UPDATED = 'updated';

    public const string DELETED = 'deleted';

    public const string RESTORED = 'restored';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    /**
     * Null retention keeps everything.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = config('kit.audit.retention_days');

        return is_numeric($days)
            ? static::query()->where('created_at', '<', now()->subDays((int) $days))
            : static::query()->whereRaw('1 = 0');
    }
}
