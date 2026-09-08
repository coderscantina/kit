<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Notification;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the bell needs, and nothing else.
 *
 * A separate query from the inbox on purpose: the badge is subscribed on
 * every screen, the list only where it is open, and two numbers changing is a
 * far smaller push than fifty rows that did not.
 *
 * Both counts ignore archived rows. Archiving is how a person says "done with
 * this", and a badge that kept counting it would make the archive pointless.
 */
#[TypeScript]
final class NotificationSummaryData extends Data
{
    public function __construct(
        public int $unseen,
        public int $total,
    ) {}

    /**
     * The one definition of "unseen" and "total" in the app: the query that
     * feeds the badge and the mutations that answer with fresh numbers both
     * come through here.
     */
    public static function forOwner(string $userId): self
    {
        // toBase(): two aggregates in one round trip, and a plain row rather
        // than a model carrying attributes the table does not have.
        $counts = Notification::query()
            ->where('notifiable_type', (new User)->getMorphClass())
            ->where('notifiable_id', $userId)
            ->whereNull('archived_at')
            ->toBase()
            ->selectRaw('count(*) as total, sum(case when read_at is null then 1 else 0 end) as unseen')
            ->first();

        return new self(
            unseen: (int) ($counts->unseen ?? 0),
            total: (int) ($counts->total ?? 0),
        );
    }
}
