<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Kit\Reactive\Invalidation\Change;
use Kit\Reactive\Invalidation\ChangeBuffer;

/**
 * Moves rows of one account's inbox between unseen, seen and archived.
 *
 * Every operation is a single UPDATE, because "mark all as read" on a
 * neglected inbox is thousands of rows and a loop of saves would be
 * thousands of round trips inside one transaction.
 *
 * An UPDATE fires no model events, so nothing would tell the reactive layer
 * the rows moved. Rather than a table-wide invalidation, which would wake
 * every open inbox in the installation, one Change is recorded carrying the
 * owner's id: the resolver dedupes computation keys, so a single change
 * against the right predicate wakes exactly this account's subscriptions and
 * nobody else's.
 *
 * @see docs/limitations.md#non-eloquent-writes-are-invisible
 */
class UpdateInboxState
{
    public function __construct(
        private readonly ChangeBuffer $changes,
    ) {}

    /**
     * @param  array<int, string>|null  $ids  null marks every unseen one
     */
    public function markSeen(User $user, ?array $ids = null): int
    {
        return $this->apply(
            $user,
            $ids,
            fn (Builder $query): Builder => $query->whereNull('read_at'),
            ['read_at' => now()],
        );
    }

    /**
     * Archiving implies seeing: a row taken out of the inbox unread would
     * otherwise keep counting against the badge forever.
     *
     * An explicit list archives whatever it names. "Archive everything" only
     * takes what has already been seen, so a full inbox cannot be swept away
     * unread by one click.
     *
     * @param  array<int, string>|null  $ids  null archives everything already seen
     */
    public function archive(User $user, ?array $ids = null): int
    {
        if ($ids !== null) {
            $this->markSeen($user, $ids);
        }

        return $this->apply(
            $user,
            $ids,
            fn (Builder $query): Builder => $ids === null
                ? $query->whereNull('archived_at')->whereNotNull('read_at')
                : $query->whereNull('archived_at'),
            ['archived_at' => now()],
        );
    }

    /**
     * Back into the inbox, still seen. Restoring something and having it
     * count as new again would be a badge nobody could trust.
     *
     * @param  array<int, string>|null  $ids  null restores everything archived
     */
    public function restore(User $user, ?array $ids = null): int
    {
        return $this->apply(
            $user,
            $ids,
            fn (Builder $query): Builder => $query->whereNotNull('archived_at'),
            ['archived_at' => null],
        );
    }

    /**
     * @param  array<int, string>|null  $ids
     * @param  callable(Builder<Notification>): Builder<Notification>  $state
     * @param  array<string, mixed>  $values
     */
    private function apply(User $user, ?array $ids, callable $state, array $values): int
    {
        /** @var Builder<Notification> $query */
        $query = Notification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id);

        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        $query = $state($query);

        // Taken before the write, and only one: the invalidation needs a row
        // to hang the owner's id on, not the whole set.
        $witness = (clone $query)->value('id');

        if ($witness === null) {
            return 0;
        }

        $affected = $query->update([...$values, 'updated_at' => now()]);

        $this->changes->record(new Change(
            table: 'notifications',
            id: (string) $witness,
            before: null,
            after: ['notifiable_id' => $user->id],
        ));

        return $affected;
    }
}
