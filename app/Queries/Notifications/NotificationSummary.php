<?php

declare(strict_types=1);

namespace App\Queries\Notifications;

use App\Data\NotificationSummaryArgs;
use App\Data\NotificationSummaryData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Attributes\ReactiveQuery;
use Kit\Reactive\Dep;
use Kit\Reactive\Query;
use Spatie\LaravelData\Data;

/**
 * The two numbers behind the bell.
 *
 * Its own query rather than a field on the inbox: the badge is subscribed on
 * every screen, the list only where it is open, and a count that changed is a
 * far cheaper push than fifty rows that did not.
 *
 * @extends Query<NotificationSummaryArgs>
 */
#[ReactiveQuery('notifications.summary', result: NotificationSummaryData::class)]
final class NotificationSummary extends Query
{
    public static function args(): string
    {
        return NotificationSummaryArgs::class;
    }

    public function authorize(Authenticatable $user, Data $args): void
    {
        if ($user->getAuthIdentifier() !== $args->userId) {
            throw new AuthorizationException;
        }
    }

    /**
     * @return array<int, Dep>
     */
    public function reads(Data $args): array
    {
        return [Dep::eq('notifications', 'notifiable_id', $args->userId)];
    }

    public function handle(Data $args): NotificationSummaryData
    {
        return NotificationSummaryData::forOwner($args->userId);
    }
}
