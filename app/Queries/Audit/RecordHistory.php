<?php

declare(strict_types=1);

namespace App\Queries\Audit;

use App\Data\AuditEntryData;
use App\Data\RecordArgs;
use App\Models\AuditEntry;
use App\Models\Concerns\Auditable;
use App\Support\Records;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Kit\Reactive\Attributes\ReactiveQuery;
use Kit\Reactive\Dep;
use Kit\Reactive\Query;
use Spatie\LaravelData\Data;

/**
 * The history of one Auditable record, newest first, live: a save anywhere
 * adds its line to every open history of that record.
 *
 * @extends Query<RecordArgs>
 */
#[ReactiveQuery('audit.history', result: AuditEntryData::class, list: true)]
final class RecordHistory extends Query
{
    public static function args(): string
    {
        return RecordArgs::class;
    }

    /** Whoever may view the record may read its history. */
    public function authorize(Authenticatable $user, Data $args): void
    {
        Records::authorize($this->gate($user), 'view', $args->type, $args->id, Auditable::class);
    }

    /**
     * Every audited write in the app lands in this one table, so without the
     * predicate each of them would wake every open history.
     *
     * @return array<int, Dep>
     */
    public function reads(Data $args): array
    {
        return [Dep::eq('audit_entries', 'subject_id', $args->id)];
    }

    /**
     * @return Collection<int, AuditEntryData>
     */
    public function handle(Data $args): mixed
    {
        $class = Records::modelClass($args->type, Auditable::class);

        if ($class === null) {
            return collect();
        }

        $entries = AuditEntry::query()
            ->with(['actor', 'impersonator'])
            ->where('subject_type', (new $class)->getMorphClass())
            ->where('subject_id', $args->id)
            ->orderByDesc('id')
            ->limit((int) config('kit.audit.history_limit', 50))
            ->get();

        return AuditEntryData::collect($entries);
    }
}
