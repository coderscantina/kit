<?php

declare(strict_types=1);

namespace App\Mutations\Notifications;

use App\Actions\Notifications\UpdateInboxState;
use App\Data\NotificationScopeArgs;
use App\Data\NotificationSummaryData;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Attributes\ReactiveMutation;
use Kit\Reactive\Mutation;
use Spatie\LaravelData\Data;

/**
 * Takes notifications out of the inbox. Nothing is deleted; pruning is a
 * scheduled job with a retention window, not a click.
 *
 * @extends Mutation<NotificationScopeArgs>
 */
#[ReactiveMutation('notifications.archive', result: NotificationSummaryData::class)]
final class ArchiveNotifications extends Mutation
{
    public function __construct(
        private readonly UpdateInboxState $inbox,
    ) {}

    public static function args(): string
    {
        return NotificationScopeArgs::class;
    }

    /** An inbox is one person's, and only its owner may move rows in it. */
    public function authorize(Authenticatable $user, Data $args): void
    {
        if ($user->getAuthIdentifier() !== $args->userId) {
            throw new AuthorizationException;
        }
    }

    /**
     * Answers with the counts rather than the rows: the badge is what the
     * caller was looking at, the list already has its own subscription, and
     * a result the size of the inbox would be pushed to every open tab.
     */
    public function handle(Data $args): NotificationSummaryData
    {
        $user = User::query()->findOrFail($args->userId);

        $this->inbox->archive($user, $args->ids);

        return NotificationSummaryData::forOwner($args->userId);
    }
}
