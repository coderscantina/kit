<?php

declare(strict_types=1);

namespace App\Queries\Notifications;

use App\Data\ListNotificationsArgs;
use App\Data\NotificationData;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Kit\Reactive\Attributes\ReactiveQuery;
use Kit\Reactive\Dep;
use Kit\Reactive\Query;
use Spatie\LaravelData\Data;

/**
 * The account's inbox, live.
 *
 * @extends Query<ListNotificationsArgs>
 */
#[ReactiveQuery('notifications.list', result: NotificationData::class, list: true)]
final class ListNotifications extends Query
{
    public static function args(): string
    {
        return ListNotificationsArgs::class;
    }

    /**
     * An inbox is one person's, and the only person allowed to ask for it is
     * the person it belongs to. There is no admin view of somebody else's
     * notifications, deliberately.
     */
    public function authorize(Authenticatable $user, Data $args): void
    {
        if ($user->getAuthIdentifier() !== $args->userId) {
            throw new AuthorizationException;
        }
    }

    /**
     * Declared rather than left to table-level tracking: `notifications` is
     * the table in this app most likely to take constant writes, and without
     * the predicate every delivery to anyone would wake every open inbox.
     *
     * @return array<int, Dep>
     */
    public function reads(Data $args): array
    {
        return [Dep::eq('notifications', 'notifiable_id', $args->userId)];
    }

    /**
     * @return Collection<int, NotificationData>
     */
    public function handle(Data $args): mixed
    {
        $status = $args->status;

        $rows = Notification::query()
            ->where('notifiable_type', (new User)->getMorphClass())
            ->where('notifiable_id', $args->userId)
            ->when($status === NotificationStatus::Archived, fn ($query) => $query->archived())
            ->when($status === NotificationStatus::Seen, fn ($query) => $query->active()->read())
            ->when($status === NotificationStatus::Unseen, fn ($query) => $query->active()->unread())
            // No status is the inbox: everything still in it.
            ->when($status === null, fn ($query) => $query->active())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit((int) config('notifications.inbox_limit', 50))
            ->get();

        return NotificationData::collect($rows);
    }
}
